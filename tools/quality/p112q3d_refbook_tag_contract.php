<?php

declare(strict_types=1);

/**
 * P112Q3D RefBook tag contract verifier.
 *
 * Public CLI tool.
 * Role:
 *   Scan Opus framework public symbols and public methods to verify that each
 *   documented API boundary has an explicit `OPUS_REFBOOK` tag block for the
 *   Reference Book generator.
 *
 * Contract:
 *   - read only framework PHP sources;
 *   - never mutate source files;
 *   - never install dependencies;
 *   - write reports only under var/reports/p112q3d_refbook_tag_contract;
 *   - strict mode fails explicitly when a public class/interface/trait/enum or
 *     public method has no local `OPUS_REFBOOK` block;
 *   - class-level tags do not silently cover method-level documentation.
 */
final class P112Q3DRefBookTagContract
{
    private string $root;
    private string $frameworkRoot;
    private string $reportRoot;

    /** @var array<int,array<string,mixed>> */
    private array $symbols = [];

    /** @var array<int,array<string,mixed>> */
    private array $classRows = [];

    /** @var array<int,array<string,mixed>> */
    private array $methodRows = [];

    public function __construct(string $root)
    {
        $this->root = rtrim($root, DIRECTORY_SEPARATOR);
        $this->frameworkRoot = $this->root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus';
        $this->reportRoot = $this->root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'p112q3d_refbook_tag_contract';
    }

    /**
     * Public CLI entrypoint.
     *
     * @param bool|null $strict Override strict mode. Null reads OPUS_P112Q3D_STRICT.
     *
     * @return int Process exit code.
     */
    public function run(?bool $strict = null): int
    {
        $strict = $strict ?? $this->isStrictMode();
        $this->assertDirectory($this->frameworkRoot, 'OPUS_FRAMEWORK_ROOT_MISSING');

        $this->symbols = $this->scanFrameworkSymbols();
        $this->buildRows();
        $summary = $this->buildSummary($strict);
        $paths = $this->writeReports($summary);

        echo 'P112Q3D_REFBOOK_TAG_CONTRACT_AUDIT_OK' . PHP_EOL;
        echo 'Symbols=' . (string) $summary['symbols'] . ' PublicMethods=' . (string) $summary['public_methods'] . PHP_EOL;
        echo 'ClassTagsMissing=' . (string) $summary['class_tags_missing'] . ' MethodTagsMissing=' . (string) $summary['method_tags_missing'] . PHP_EOL;
        echo 'JSON=' . $paths['json'] . PHP_EOL;
        echo 'MD=' . $paths['md'] . PHP_EOL;
        echo 'HTML=' . $paths['html'] . PHP_EOL;

        if ($strict && ($summary['class_tags_missing'] > 0 || $summary['method_tags_missing'] > 0)) {
            fwrite(
                STDERR,
                'P112Q3D_REFBOOK_TAG_CONTRACT_STRICT_FAILED: Classes=' . (string) $summary['class_tags_missing']
                . ' Methods=' . (string) $summary['method_tags_missing'] . PHP_EOL
            );

            return 2;
        }

        if ($strict) {
            echo 'P112Q3D_REFBOOK_TAG_CONTRACT_STRICT_OK' . PHP_EOL;
        }

        return 0;
    }

    /** @return array<int,array<string,mixed>> */
    private function scanFrameworkSymbols(): array
    {
        $symbols = [];
        foreach ($this->phpFiles($this->frameworkRoot) as $file) {
            foreach ($this->parsePhpSymbols($this->readFile($file), $file) as $symbol) {
                $symbols[] = $symbol;
            }
        }

        usort($symbols, static fn (array $a, array $b): int => strcmp((string) $a['fqcn'], (string) $b['fqcn']));

        return $symbols;
    }

    /** @return array<int,array<string,mixed>> */
    private function parsePhpSymbols(string $source, string $file): array
    {
        $tokens = token_get_all($source);
        $namespace = '';
        $symbols = [];
        $count = count($tokens);
        $classTokens = [T_CLASS => 'class', T_INTERFACE => 'interface', T_TRAIT => 'trait'];

        if (defined('T_ENUM')) {
            $classTokens[(int) constant('T_ENUM')] = 'enum';
        }

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($this->isToken($token, T_NAMESPACE)) {
                $namespace = $this->readNamespace($tokens, $i + 1);
                continue;
            }

            if (!is_array($token) || !isset($classTokens[$token[0]])) {
                continue;
            }

            if ($this->isPseudoClassToken($tokens, $i)) {
                continue;
            }

            $nameIndex = $this->nextMeaningfulTokenIndex($tokens, $i + 1);
            if ($nameIndex === null || !$this->isToken($tokens[$nameIndex], T_STRING)) {
                continue;
            }

            $bodyStart = $this->findNextChar($tokens, $nameIndex + 1, '{');
            if ($bodyStart === null) {
                continue;
            }

            $relative = $this->relativePath($file);
            $shortName = (string) $tokens[$nameIndex][1];
            $kind = $classTokens[$token[0]];
            $classComments = $this->collectPrecedingComments($tokens, $i);
            $classTag = $this->hasRefBookTag($classComments);

            $symbols[] = [
                'kind' => $kind,
                'namespace' => $namespace,
                'short_name' => $shortName,
                'fqcn' => $namespace === '' ? $shortName : $namespace . '\\' . $shortName,
                'file' => $relative,
                'domain' => $this->domainFromPath($relative),
                'line' => (int) $token[2],
                'refbook_tag' => $classTag,
                'tag_context' => $classComments,
                'public_methods' => $this->parsePublicMethods($tokens, $bodyStart + 1, $kind, $relative),
            ];

            $i = $this->findMatchingBrace($tokens, $bodyStart) ?? $bodyStart;
        }

        return $symbols;
    }

    /**
     * @param array<int,mixed> $tokens
     * @return array<int,array<string,mixed>>
     */
    private function parsePublicMethods(array $tokens, int $start, string $kind, string $relativeFile): array
    {
        $methods = [];
        $depth = 1;
        $visibility = null;
        $count = count($tokens);

        for ($i = $start; $i < $count && $depth > 0; $i++) {
            $token = $tokens[$i];

            if ($token === '{') {
                $depth++;
                continue;
            }

            if ($token === '}') {
                $depth--;
                continue;
            }

            if ($depth !== 1) {
                continue;
            }

            if ($this->isToken($token, T_PUBLIC)) {
                $visibility = 'public';
                continue;
            }

            if ($this->isToken($token, T_PROTECTED)) {
                $visibility = 'protected';
                continue;
            }

            if ($this->isToken($token, T_PRIVATE)) {
                $visibility = 'private';
                continue;
            }

            if (!$this->isToken($token, T_FUNCTION)) {
                continue;
            }

            $nameIndex = $this->nextMeaningfulTokenIndex($tokens, $i + 1);
            if ($nameIndex === null || $tokens[$nameIndex] === '&') {
                $nameIndex = $this->nextMeaningfulTokenIndex($tokens, ($nameIndex ?? $i) + 1);
            }

            if ($nameIndex === null || !$this->isToken($tokens[$nameIndex], T_STRING)) {
                $visibility = null;
                continue;
            }

            $methodVisibility = $visibility ?? ($kind === 'interface' ? 'public' : 'public');
            if ($methodVisibility === 'public') {
                $comments = $this->collectPrecedingComments($tokens, $i);
                $methods[] = [
                    'name' => (string) $tokens[$nameIndex][1],
                    'line' => (int) $tokens[$nameIndex][2],
                    'file' => $relativeFile,
                    'refbook_tag' => $this->hasRefBookTag($comments),
                    'tag_context' => $comments,
                ];
            }

            $visibility = null;
        }

        return $methods;
    }

    private function buildRows(): void
    {
        foreach ($this->symbols as $symbol) {
            $this->classRows[] = [
                'level' => 'class',
                'domain' => $symbol['domain'],
                'kind' => $symbol['kind'],
                'symbol' => $symbol['fqcn'],
                'method' => '',
                'file' => $symbol['file'],
                'line' => $symbol['line'],
                'refbook_tag' => $symbol['refbook_tag'],
                'status' => $symbol['refbook_tag'] ? 'TAG_OK' : 'TAG_MISSING',
            ];

            foreach ($symbol['public_methods'] as $method) {
                $this->methodRows[] = [
                    'level' => 'method',
                    'domain' => $symbol['domain'],
                    'kind' => $symbol['kind'],
                    'symbol' => $symbol['fqcn'],
                    'method' => $method['name'],
                    'file' => $method['file'],
                    'line' => $method['line'],
                    'refbook_tag' => $method['refbook_tag'],
                    'status' => $method['refbook_tag'] ? 'TAG_OK' : 'TAG_MISSING',
                ];
            }
        }

        usort($this->classRows, static fn (array $a, array $b): int => strcmp((string) $a['symbol'], (string) $b['symbol']));
        usort($this->methodRows, static fn (array $a, array $b): int => strcmp((string) $a['symbol'] . '::' . (string) $a['method'], (string) $b['symbol'] . '::' . (string) $b['method']));
    }

    /** @return array<string,mixed> */
    private function buildSummary(bool $strict): array
    {
        $summary = [
            'generated_at' => gmdate('c'),
            'strict_mode' => $strict,
            'symbols' => count($this->classRows),
            'public_methods' => count($this->methodRows),
            'class_tags_ok' => 0,
            'class_tags_missing' => 0,
            'method_tags_ok' => 0,
            'method_tags_missing' => 0,
            'by_domain' => [],
            'note' => 'Class-level OPUS_REFBOOK blocks and method-level OPUS_REFBOOK blocks are checked separately. A class tag never silently covers all methods.',
        ];

        foreach ($this->classRows as $row) {
            $domain = (string) $row['domain'];
            $status = (string) $row['status'];
            $summary['by_domain'][$domain]['class'][$status] = ($summary['by_domain'][$domain]['class'][$status] ?? 0) + 1;
            if ($status === 'TAG_OK') {
                $summary['class_tags_ok']++;
            } else {
                $summary['class_tags_missing']++;
            }
        }

        foreach ($this->methodRows as $row) {
            $domain = (string) $row['domain'];
            $status = (string) $row['status'];
            $summary['by_domain'][$domain]['method'][$status] = ($summary['by_domain'][$domain]['method'][$status] ?? 0) + 1;
            if ($status === 'TAG_OK') {
                $summary['method_tags_ok']++;
            } else {
                $summary['method_tags_missing']++;
            }
        }

        ksort($summary['by_domain']);

        return $summary;
    }

    /**
     * @param array<string,mixed> $summary
     * @return array<string,string>
     */
    private function writeReports(array $summary): array
    {
        if (!is_dir($this->reportRoot) && !mkdir($this->reportRoot, 0777, true) && !is_dir($this->reportRoot)) {
            throw new RuntimeException('P112Q3D_REPORT_ROOT_CREATE_FAILED: ' . $this->reportRoot);
        }

        $stamp = gmdate('Ymd_His');
        $payload = [
            'summary' => $summary,
            'classes' => $this->classRows,
            'methods' => $this->methodRows,
        ];

        $jsonPath = $this->reportRoot . DIRECTORY_SEPARATOR . 'P112Q3D_REFBOOK_TAG_CONTRACT_' . $stamp . '.json';
        $mdPath = $this->reportRoot . DIRECTORY_SEPARATOR . 'P112Q3D_REFBOOK_TAG_CONTRACT_' . $stamp . '.md';
        $htmlPath = $this->reportRoot . DIRECTORY_SEPARATOR . 'P112Q3D_REFBOOK_TAG_CONTRACT_' . $stamp . '.html';

        $this->writeFile($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
        $this->writeFile($mdPath, $this->renderMarkdown($summary));
        $this->writeFile($htmlPath, $this->renderHtml($summary));

        foreach (['json' => $jsonPath, 'md' => $mdPath, 'html' => $htmlPath] as $type => $path) {
            $this->writeFile($this->reportRoot . DIRECTORY_SEPARATOR . 'latest.' . $type, $this->readFile($path));
        }

        return ['json' => $jsonPath, 'md' => $mdPath, 'html' => $htmlPath];
    }

    /** @param array<string,mixed> $summary */
    private function renderMarkdown(array $summary): string
    {
        $out = [];
        $out[] = '# P112Q3D â€” Opus RefBook Tag Contract';
        $out[] = '';
        $out[] = 'Generated at: `' . (string) $summary['generated_at'] . '`';
        $out[] = '';
        $out[] = '## Contract';
        $out[] = '';
        $out[] = '- Every public class/interface/trait/enum must have an `OPUS_REFBOOK` block.';
        $out[] = '- Every public method must have its own local `OPUS_REFBOOK` block.';
        $out[] = '- A class-level tag never silently covers all methods.';
        $out[] = '';
        $out[] = '## Summary';
        $out[] = '';
        $out[] = '| Metric | Count |';
        $out[] = '|---|---:|';
        $out[] = '| Symbols | ' . (string) $summary['symbols'] . ' |';
        $out[] = '| Public methods | ' . (string) $summary['public_methods'] . ' |';
        $out[] = '| Class tags OK | ' . (string) $summary['class_tags_ok'] . ' |';
        $out[] = '| Class tags missing | ' . (string) $summary['class_tags_missing'] . ' |';
        $out[] = '| Method tags OK | ' . (string) $summary['method_tags_ok'] . ' |';
        $out[] = '| Method tags missing | ' . (string) $summary['method_tags_missing'] . ' |';
        $out[] = '';
        $out[] = '## Missing class tags';
        $out[] = '';
        $out[] = '| Domain | Symbol | File |';
        $out[] = '|---|---|---|';
        foreach ($this->classRows as $row) {
            if ($row['status'] !== 'TAG_MISSING') {
                continue;
            }
            $out[] = '| ' . $this->escapeMd((string) $row['domain']) . ' | `' . $this->escapeMd((string) $row['symbol']) . '` | `' . $this->escapeMd((string) $row['file']) . ':' . (string) $row['line'] . '` |';
        }
        $out[] = '';
        $out[] = '## Missing method tags';
        $out[] = '';
        $out[] = '| Domain | Symbol | Method | File |';
        $out[] = '|---|---|---|---|';
        foreach ($this->methodRows as $row) {
            if ($row['status'] !== 'TAG_MISSING') {
                continue;
            }
            $out[] = '| ' . $this->escapeMd((string) $row['domain']) . ' | `' . $this->escapeMd((string) $row['symbol']) . '` | `' . $this->escapeMd((string) $row['method']) . '` | `' . $this->escapeMd((string) $row['file']) . ':' . (string) $row['line'] . '` |';
        }

        return implode(PHP_EOL, $out) . PHP_EOL;
    }

    /** @param array<string,mixed> $summary */
    private function renderHtml(array $summary): string
    {
        $classRows = $this->renderHtmlRows($this->classRows);
        $methodRows = $this->renderHtmlRows($this->methodRows);

        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>P112Q3D RefBook Tag Contract</title>'
            . '<style>body{font-family:Arial,sans-serif;background:#0b1220;color:#eef4ff;margin:0;padding:24px}h1{margin:0 0 8px}.muted{color:#9fb2d8}.cards{display:flex;gap:12px;flex-wrap:wrap;margin:22px 0}.card{border:1px solid #263957;border-radius:14px;padding:14px 18px;background:#101b2d}.card strong{display:block;font-size:26px}.note{border-left:4px solid #60a5fa;padding:10px 14px;background:#0f1a2c;margin:18px 0}table{border-collapse:collapse;width:100%;background:#101b2d;border-radius:12px;overflow:hidden;margin:16px 0 28px}th,td{border-bottom:1px solid #263957;padding:9px 10px;text-align:left;font-size:13px}th{background:#17243a}.ok td:first-child{color:#4ade80}.miss td:first-child{color:#fb7185}code{color:#fde68a}</style></head><body>'
            . '<h1>P112Q3D â€” Opus RefBook Tag Contract</h1><p class="muted">Generated at ' . $this->h((string) $summary['generated_at']) . '</p>'
            . '<div class="note">Class-level tags and method-level tags are separate. A class tag never silently covers all methods.</div>'
            . '<div class="cards"><div class="card"><span>Symbols</span><strong>' . $this->h((string) $summary['symbols']) . '</strong></div><div class="card"><span>Public methods</span><strong>' . $this->h((string) $summary['public_methods']) . '</strong></div><div class="card"><span>Class tags missing</span><strong>' . $this->h((string) $summary['class_tags_missing']) . '</strong></div><div class="card"><span>Method tags missing</span><strong>' . $this->h((string) $summary['method_tags_missing']) . '</strong></div></div>'
            . '<h2>Classes / interfaces / traits / enums</h2><table><thead><tr><th>Status</th><th>Domain</th><th>Symbol</th><th>File</th></tr></thead><tbody>' . $classRows . '</tbody></table>'
            . '<h2>Public methods</h2><table><thead><tr><th>Status</th><th>Domain</th><th>Symbol</th><th>Method</th><th>File</th></tr></thead><tbody>' . $methodRows . '</tbody></table>'
            . '</body></html>' . PHP_EOL;
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function renderHtmlRows(array $rows): string
    {
        $html = '';
        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $class = $status === 'TAG_OK' ? 'ok' : 'miss';
            $html .= '<tr class="' . $class . '"><td>' . $this->h($status) . '</td><td>' . $this->h((string) $row['domain']) . '</td><td><code>' . $this->h((string) $row['symbol']) . '</code></td>';
            if (($row['level'] ?? '') === 'method') {
                $html .= '<td><code>' . $this->h((string) $row['method']) . '</code></td>';
            }
            $html .= '<td><code>' . $this->h((string) $row['file']) . ':' . $this->h((string) $row['line']) . '</code></td></tr>' . PHP_EOL;
        }

        return $html;
    }

    /**
     * @param array<int,mixed> $tokens
     * @return string[]
     */
    private function collectPrecedingComments(array $tokens, int $index): array
    {
        $comments = [];
        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            if ($this->isDeclarationModifierToken($token)) {
                continue;
            }

            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                array_unshift($comments, (string) $token[1]);
                continue;
            }

            if ($token === ']' || $token === '[' || $this->isToken($token, T_ATTRIBUTE)) {
                continue;
            }

            break;
        }

        return $comments;
    }

    /** @param string[] $comments */
    private function hasRefBookTag(array $comments): bool
    {
        $block = implode(PHP_EOL, $comments);

        return str_contains($block, 'OPUS_REFBOOK:') && str_contains($block, 'END_OPUS_REFBOOK');
    }


    private function isDeclarationModifierToken(mixed $token): bool
    {
        if (!is_array($token)) {
            return false;
        }

        $modifiers = [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_FINAL, T_ABSTRACT];
        if (defined('T_READONLY')) {
            $modifiers[] = (int) constant('T_READONLY');
        }

        return in_array($token[0], $modifiers, true);
    }

    /** @return string[] */
    private function phpFiles(string $root): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }
            if (strtolower($fileInfo->getExtension()) !== 'php') {
                continue;
            }
            $path = $fileInfo->getPathname();
            if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
                continue;
            }
            $files[] = $path;
        }
        sort($files);

        return $files;
    }

    /** @param array<int,mixed> $tokens */
    private function readNamespace(array $tokens, int $start): string
    {
        $parts = [];
        $count = count($tokens);
        for ($i = $start; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token === ';' || $token === '{') {
                break;
            }
            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                $parts[] = $token[1];
            } elseif ($token === '\\') {
                $parts[] = '\\';
            }
        }

        return trim(implode('', $parts), '\\');
    }

    /** @param array<int,mixed> $tokens */
    private function isPseudoClassToken(array $tokens, int $index): bool
    {
        $previous = $this->previousMeaningfulTokenIndex($tokens, $index - 1);
        if ($previous === null) {
            return false;
        }

        return $tokens[$previous] === T_DOUBLE_COLON || $this->isToken($tokens[$previous], T_NEW);
    }

    /** @param array<int,mixed> $tokens */
    private function nextMeaningfulTokenIndex(array $tokens, int $start): ?int
    {
        $count = count($tokens);
        for ($i = $start; $i < $count; $i++) {
            $token = $tokens[$i];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $i;
        }

        return null;
    }

    /** @param array<int,mixed> $tokens */
    private function previousMeaningfulTokenIndex(array $tokens, int $start): ?int
    {
        for ($i = $start; $i >= 0; $i--) {
            $token = $tokens[$i];
            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $i;
        }

        return null;
    }

    /** @param array<int,mixed> $tokens */
    private function findNextChar(array $tokens, int $start, string $char): ?int
    {
        $count = count($tokens);
        for ($i = $start; $i < $count; $i++) {
            if ($tokens[$i] === $char) {
                return $i;
            }
        }

        return null;
    }

    /** @param array<int,mixed> $tokens */
    private function findMatchingBrace(array $tokens, int $start): ?int
    {
        $depth = 0;
        $count = count($tokens);
        for ($i = $start; $i < $count; $i++) {
            if ($tokens[$i] === '{') {
                $depth++;
            } elseif ($tokens[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function isToken(mixed $token, int $type): bool
    {
        return is_array($token) && $token[0] === $type;
    }

    private function domainFromPath(string $relativePath): string
    {
        $normalized = str_replace('\\', '/', $relativePath);
        $parts = explode('/', $normalized);
        $index = array_search('Opus', $parts, true);
        if ($index === false || !isset($parts[$index + 1])) {
            return 'UNKNOWN';
        }

        return strtoupper((string) $parts[$index + 1]);
    }

    private function relativePath(string $path): string
    {
        $root = $this->root . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $root)) {
            return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root)));
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', $path);
    }

    private function assertDirectory(string $path, string $code): void
    {
        if (!is_dir($path)) {
            throw new RuntimeException($code . ': ' . $path);
        }
    }

    private function readFile(string $path): string
    {
        $content = file_get_contents($path);
        if (!is_string($content)) {
            throw new RuntimeException('P112Q3D_FILE_READ_FAILED: ' . $path);
        }

        return $content;
    }

    private function writeFile(string $path, string $content): void
    {
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException('P112Q3D_FILE_WRITE_FAILED: ' . $path);
        }
    }

    private function isStrictMode(): bool
    {
        return (string) getenv('OPUS_P112Q3D_STRICT') === '1';
    }

    private function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapeMd(string $value): string
    {
        return str_replace('|', '\\|', $value);
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $root = dirname(__DIR__, 2);

    try {
        exit((new P112Q3DRefBookTagContract($root))->run());
    } catch (Throwable $exception) {
        fwrite(STDERR, 'P112Q3D_REFBOOK_TAG_CONTRACT_FAILED: ' . $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}
