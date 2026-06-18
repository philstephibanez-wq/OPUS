<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Service;

use Opus\Documentation\RuntimeClassCatalog;
use RuntimeException;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Build the RefBook read model from the live OPUS runtime class catalog.
 *
 * Contract:
 *   OPUS is the source of truth. This repository must not read a persisted
 *   symbol manifest, must not invent UNCLASSIFIED domains and must fail
 *   explicitly when the live catalog reports diagnostics.
 */
final class ReferenceRuntimeSnapshotRepository implements ReferenceSnapshotRepositoryInterface
{
    public function __construct(private readonly string $opusRoot)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function load(): array
    {
        if (!class_exists(RuntimeClassCatalog::class)) {
            throw new RuntimeException('OPUS_REFBOOK_LIVE_CATALOG_CLASS_MISSING=' . RuntimeClassCatalog::class);
        }

        $sourceRoot = rtrim(str_replace('\\', '/', $this->opusRoot), '/') . '/framework/Opus';
        $catalog = new RuntimeClassCatalog($sourceRoot);
        $classes = $catalog->all();
        $diagnostics = $catalog->diagnostics();

        if ($diagnostics !== []) {
            throw new RuntimeException('OPUS_REFBOOK_LIVE_CATALOG_DIAGNOSTICS=' . implode(' | ', $diagnostics));
        }

        $symbols = [];
        foreach ($classes as $index => $classInfo) {
            $class = $classInfo->toArray();
            $symbolName = (string) ($class['name'] ?? '');
            if ($symbolName === '') {
                throw new RuntimeException('OPUS_REFBOOK_LIVE_CLASS_NAME_MISSING_AT=' . (string) $index);
            }

            $domain = trim((string) ($class['domain'] ?? ''));
            if ($domain === '' || strtoupper($domain) === 'UNCLASSIFIED') {
                throw new RuntimeException('OPUS_REFBOOK_DOMAIN_UNRESOLVED=' . $symbolName);
            }

            $methods = [];
            foreach ($this->listValue($class['public_methods'] ?? []) as $method) {
                if (!is_array($method)) {
                    continue;
                }

                $methods[] = [
                    'name' => (string) ($method['name'] ?? ''),
                    'signature' => $this->methodSignature($method),
                    'visibility' => (string) ($method['visibility'] ?? 'public'),
                    'role' => $this->firstDocLine((string) ($method['doc_comment'] ?? '')),
                    'behavior' => '',
                    'preconditions' => [],
                    'postconditions' => [],
                    'side_effects' => [],
                    'errors' => [],
                    'examples' => [],
                    'diagrams' => [],
                ];
            }

            $classDoc = (string) ($class['doc_comment'] ?? '');
            $responsibility = $this->docSectionText($classDoc, 'Responsibility');
            $contract = $this->docSectionList($classDoc, 'Contract');

            $symbol = [
                'symbol' => $symbolName,
                'name' => (string) ($class['short_name'] ?? $this->shortName($symbolName)),
                'short_name' => (string) ($class['short_name'] ?? $this->shortName($symbolName)),
                'kind' => (string) ($class['type'] ?? 'class'),
                'type' => (string) ($class['type'] ?? 'class'),
                'namespace' => (string) ($class['namespace'] ?? $this->namespaceName($symbolName)),
                'file' => (string) ($class['file'] ?? ''),
                'domain' => $domain,
                'role' => $this->firstDocLine($classDoc),
                'visibility' => 'public',
                'examples' => [],
                'diagrams' => [],
                'example_assets' => [],
                'diagram_assets' => [],
                'methods' => $methods,
            ];

            if ($responsibility !== '') {
                $symbol['responsibility'] = $responsibility;
            }

            if ($contract !== []) {
                $symbol['contract'] = $contract;
            }

            $symbols[] = $symbol;
        }

        return [
            'schema' => 'OPUS_REFBOOK_RUNTIME_MANIFEST_V1',
            'schema_version' => 'opus-live-runtime-catalog/v1',
            'generated_at' => gmdate('c'),
            'source_root' => $sourceRoot,
            'producer' => RuntimeClassCatalog::class,
            'summary' => [
                'symbol_count' => count($symbols),
                'source' => 'live-opus-runtime-class-catalog',
            ],
            'api' => [
                'version' => 'opus-refbook-live/v1',
                'style' => 'LOCAL_RUNTIME',
                'read_only' => true,
                'base_path' => '/api/refbook',
                'endpoints' => [],
            ],
            'validation' => [
                'ok' => true,
                'diagnostics' => [],
            ],
            'documentation_assets' => [
                'examples' => [],
                'diagrams' => [],
            ],
            'asset_integrity' => [
                'ok' => true,
                'missing_count' => 0,
                'missing' => [],
            ],
            'runtime' => [
                'source' => 'live-opus-runtime-class-catalog',
                'opus_root' => $this->opusRoot,
                'read_only' => true,
            ],
            'symbols' => $symbols,
        ];
    }

    /**
     * @param array<string,mixed> $method
     */
    private function methodSignature(array $method): string
    {
        $visibility = (string) ($method['visibility'] ?? 'public');
        $static = ($method['static'] ?? false) === true ? ' static' : '';
        $name = (string) ($method['name'] ?? 'method');
        $params = [];

        foreach ($this->listValue($method['parameters'] ?? []) as $parameter) {
            if (!is_array($parameter)) {
                continue;
            }

            $params[] = $this->parameterSignature($parameter);
        }

        $returnType = (string) ($method['return_type'] ?? '');
        $return = $returnType !== '' ? ': ' . $returnType : '';

        return $visibility . $static . ' function ' . $name . '(' . implode(', ', $params) . ')' . $return;
    }

    /**
     * @param array<string,mixed> $parameter
     */
    private function parameterSignature(array $parameter): string
    {
        $name = (string) ($parameter['name'] ?? 'param');
        $type = (string) ($parameter['type'] ?? '');
        $prefix = $type !== '' ? $type . ' ' : '';
        $default = ($parameter['optional'] ?? false) === true ? ' = ...' : '';

        return $prefix . '$' . $name . $default;
    }

    private function firstDocLine(string $doc): string
    {
        $doc = trim(str_replace(["\r\n", "\r"], "\n", $doc));
        if ($doc === '') {
            return '';
        }

        foreach (explode("\n", $doc) as $line) {
            $line = trim($line, " \t/*");
            if ($line !== '' && !str_starts_with($line, '@')) {
                return $line;
            }
        }

        return '';
    }

    private function docSectionText(string $doc, string $section): string
    {
        return implode(' ', $this->docSectionList($doc, $section));
    }

    /** @return list<string> */
    private function docSectionList(string $doc, string $section): array
    {
        $target = strtolower($section) . ':';
        $collect = false;
        $lines = [];

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $doc)) as $rawLine) {
            $line = trim($rawLine, " \t/*");
            if ($line === '') {
                continue;
            }

            if ($this->isDocSectionHeader($line)) {
                if ($collect) {
                    break;
                }

                $collect = strtolower($line) === $target;
                continue;
            }

            if (!$collect) {
                continue;
            }

            if (str_starts_with($line, '@')) {
                break;
            }

            $line = trim($line);
            $line = preg_replace('/^-\s*/', '', $line) ?? $line;
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function isDocSectionHeader(string $line): bool
    {
        return preg_match('/^[A-Za-z_ ]+:$/', $line) === 1;
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return (string) end($parts);
    }

    private function namespaceName(string $fqcn): string
    {
        $position = strrpos($fqcn, '\\');

        return $position === false ? '' : substr($fqcn, 0, $position);
    }

    /** @return list<mixed> */
    private function listValue(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }
}
