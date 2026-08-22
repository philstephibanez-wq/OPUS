<?php
declare(strict_types=1);

namespace Opus\Fsm\Definition;

use ParseError;
use RuntimeException;

/**
 * Deterministic managed-source editor for developer-programmed EFSM handlers.
 *
 * No eval is used. Only callable expressions inside explicit managed regions
 * can be created or updated. The complete candidate PHP source must parse
 * successfully before it can be returned to a caller for atomic persistence.
 */
final class FsmHandlerSourceEditor implements FsmHandlerSourceEditorInterface
{
    private const ID_PATTERN = '/^[a-z][a-z0-9_:-]{0,127}$/D';
    private const MAX_CODE_BYTES = 16384;

    /** @var array<string,true> */
    private const KINDS = [
        'guard' => true,
        'action' => true,
    ];

    public function catalog(string $source): array
    {
        $this->assertSource($source);

        return [
            'guard' => $this->entries($source, 'guard'),
            'action' => $this->entries($source, 'action'),
        ];
    }

    public function upsert(
        string $source,
        string $kind,
        string $id,
        string $code,
        string $mode
    ): array {
        $this->assertSource($source);
        $kind = $this->kind($kind);
        $id = $this->id($id);
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['create', 'update'], true)) {
            throw new RuntimeException(
                'OPUS_EFSM_HANDLER_SOURCE_MODE_INVALID:' . $mode
            );
        }

        $code = $this->code($code);
        $entries = $this->entries($source, $kind);
        $existing = null;
        foreach ($entries as $entry) {
            if ($entry['id'] === $id) {
                $existing = $entry;
                break;
            }
        }

        if ($mode === 'create' && is_array($existing)) {
            throw new RuntimeException(
                'OPUS_EFSM_HANDLER_SOURCE_ALREADY_EXISTS:' . $kind . ':' . $id
            );
        }
        if ($mode === 'update' && !is_array($existing)) {
            throw new RuntimeException(
                'OPUS_EFSM_HANDLER_SOURCE_UNKNOWN:' . $kind . ':' . $id
            );
        }

        $block = $this->block($kind, $id, $code);
        if (is_array($existing)) {
            $oldBlock = (string) ($existing['_block'] ?? '');
            if ($oldBlock === '' || substr_count($source, $oldBlock) !== 1) {
                throw new RuntimeException(
                    'OPUS_EFSM_HANDLER_SOURCE_BLOCK_AMBIGUOUS:'
                    . $kind . ':' . $id
                );
            }
            $candidate = str_replace($oldBlock, $block, $source);
        } else {
            [$start, $end] = $this->regionBounds($source, $kind);
            unset($start);
            $candidate = substr($source, 0, $end)
                . $block
                . substr($source, $end);
        }

        $this->assertPhpSyntax($candidate);
        /* Reparse the complete candidate to verify marker/key consistency. */
        $candidateCatalog = $this->catalog($candidate);
        $found = false;
        foreach ($candidateCatalog[$kind] as $entry) {
            if ($entry['id'] === $id
                && hash_equals(
                    hash('sha256', $code),
                    (string) $entry['sha256']
                )) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new RuntimeException(
                'OPUS_EFSM_HANDLER_SOURCE_VERIFY_FAILED:' . $kind . ':' . $id
            );
        }

        return [
            'source' => $candidate,
            'kind' => $kind,
            'id' => $id,
            'mode' => $mode,
            'created' => $existing === null,
            'handler_sha256' => hash('sha256', $code),
        ];
    }

    /** @return list<array{id:string,code:string,sha256:string,_block:string}> */
    private function entries(string $source, string $kind): array
    {
        [$start, $end] = $this->regionBounds($source, $kind);
        $region = substr($source, $start, $end - $start);
        $quotedKind = preg_quote($kind, '~');
        $pattern = '~'
            . '/\* OPUS_EFSM_HANDLER_BEGIN:' . $quotedKind
            . ':([a-z][a-z0-9_:-]{0,127}) \*/\R'
            . '(.*?)'
            . '/\* OPUS_EFSM_HANDLER_END:' . $quotedKind . ':\1 \*/\R?'
            . '~s';

        preg_match_all(
            $pattern,
            $region,
            $matches,
            PREG_SET_ORDER
        );

        $entries = [];
        $seen = [];
        foreach ($matches as $match) {
            $id = $this->id((string) ($match[1] ?? ''));
            if (isset($seen[$id])) {
                throw new RuntimeException(
                    'OPUS_EFSM_HANDLER_SOURCE_DUPLICATE:' . $kind . ':' . $id
                );
            }
            $seen[$id] = true;
            $inner = (string) ($match[2] ?? '');
            $prefix = "            '" . $id . "' => ";
            if (!str_starts_with($inner, $prefix)) {
                throw new RuntimeException(
                    'OPUS_EFSM_HANDLER_SOURCE_ENTRY_INVALID:' . $kind . ':' . $id
                );
            }
            $payload = rtrim(substr($inner, strlen($prefix)));
            if (!str_ends_with($payload, ',')) {
                throw new RuntimeException(
                    'OPUS_EFSM_HANDLER_SOURCE_ENTRY_INVALID:' . $kind . ':' . $id
                );
            }
            $code = trim(substr($payload, 0, -1));
            $this->code($code);
            $entries[] = [
                'id' => $id,
                'code' => $code,
                'sha256' => hash('sha256', $code),
                '_block' => (string) $match[0],
            ];
        }

        /* Any unmanaged text inside a region is a contract violation. */
        $stripped = preg_replace($pattern, '', $region);
        if (!is_string($stripped) || trim($stripped) !== '') {
            throw new RuntimeException(
                'OPUS_EFSM_HANDLER_SOURCE_REGION_DIRTY:' . $kind
            );
        }

        return $entries;
    }

    /** @return array{0:int,1:int} */
    private function regionBounds(string $source, string $kind): array
    {
        $begin = '/* OPUS_EFSM_HANDLER_REGION_BEGIN:' . $kind . ' */';
        $end = '/* OPUS_EFSM_HANDLER_REGION_END:' . $kind . ' */';
        if (substr_count($source, $begin) !== 1
            || substr_count($source, $end) !== 1) {
            throw new RuntimeException(
                'OPUS_EFSM_HANDLER_SOURCE_REGION_INVALID:' . $kind
            );
        }

        $beginPos = strpos($source, $begin);
        $endPos = strpos($source, $end);
        if (!is_int($beginPos) || !is_int($endPos) || $endPos <= $beginPos) {
            throw new RuntimeException(
                'OPUS_EFSM_HANDLER_SOURCE_REGION_INVALID:' . $kind
            );
        }

        $start = $beginPos + strlen($begin);
        if (substr($source, $start, 2) === "\r\n") {
            $start += 2;
        } elseif (substr($source, $start, 1) === "\n") {
            $start += 1;
        } else {
            throw new RuntimeException(
                'OPUS_EFSM_HANDLER_SOURCE_REGION_NEWLINE_INVALID:' . $kind
            );
        }

        return [$start, $endPos];
    }

    private function block(string $kind, string $id, string $code): string
    {
        $newline = "\n";
        return '            /* OPUS_EFSM_HANDLER_BEGIN:' . $kind . ':' . $id . ' */'
            . $newline
            . "            '" . $id . "' => " . $code . ','
            . $newline
            . '            /* OPUS_EFSM_HANDLER_END:' . $kind . ':' . $id . ' */'
            . $newline;
    }

    private function kind(string $kind): string
    {
        $kind = strtolower(trim($kind));
        if (!isset(self::KINDS[$kind])) {
            throw new RuntimeException(
                'OPUS_EFSM_HANDLER_SOURCE_KIND_INVALID:' . $kind
            );
        }
        return $kind;
    }

    private function id(string $id): string
    {
        $id = trim($id);
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            throw new RuntimeException(
                'OPUS_EFSM_HANDLER_SOURCE_ID_INVALID:' . $id
            );
        }
        return $id;
    }

    private function code(string $code): string
    {
        $code = trim(str_replace("\r\n", "\n", $code));
        if ($code === ''
            || strlen($code) > self::MAX_CODE_BYTES
            || str_contains($code, "\0")
            || str_contains($code, '<?')
            || str_contains($code, '?>')
            || str_contains($code, 'OPUS_EFSM_HANDLER_')
            || preg_match('/^(?:static\s+)?function\s*\(/D', $code) !== 1) {
            throw new RuntimeException(
                'OPUS_EFSM_HANDLER_SOURCE_CODE_INVALID'
            );
        }
        return $code;
    }

    private function assertSource(string $source): void
    {
        if ($source === '' || str_contains($source, "\0")) {
            throw new RuntimeException(
                'OPUS_EFSM_HANDLER_SOURCE_INVALID'
            );
        }
        foreach (array_keys(self::KINDS) as $kind) {
            $this->regionBounds($source, $kind);
        }
        $this->assertPhpSyntax($source);
    }

    private function assertPhpSyntax(string $source): void
    {
        try {
            token_get_all($source, TOKEN_PARSE);
        } catch (ParseError $error) {
            throw new RuntimeException(
                'OPUS_EFSM_HANDLER_SOURCE_PHP_INVALID',
                0,
                $error
            );
        }
    }
}