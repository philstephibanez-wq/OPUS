<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Service;


/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Build navigable catalog data from the RefBook runtime snapshot.
 *
 * Responsibility:
 *   Group source symbols by domain, expose domain and symbol lookup helpers,
 *   calculate read-only counters, expose asset diagnostics, and attach parsed
 *   diagram ViewModel data used by RefBook pages.
 *
 * Contract:
 *   Data preparation only. No HTTP routing, no HTML rendering, no fallback source.
 *   Missing documentation assets remain diagnostics; no placeholder content is generated.
 */
final class ReferenceCatalogService
{
    private readonly ReferenceDiagramModelBuilder $diagramModelBuilder;
    private readonly ReferenceApiCallService $apiCallService;

    public function __construct(
        private readonly ReferenceSnapshotRepositoryInterface $repository,
        private readonly ?ReferenceContentService $content = null
    ) {
        $this->diagramModelBuilder = new ReferenceDiagramModelBuilder();
        $this->apiCallService = new ReferenceApiCallService();
    }

    /**
     * Return the global catalog overview displayed by home and API pages.
     *
     * @return array<string,mixed>
     */
    public function overview(): array
    {
        $manifest = $this->repository->load();
        $domains = $this->domains($manifest);
        $assetDiagnostics = $this->assetDiagnosticsFromManifest($manifest);

        return [
            'manifest' => $manifest,
            'domains' => $domains,
            'symbol_count' => count($this->symbols($manifest)),
            'domain_count' => count($domains),
            'api' => $manifest['api'] ?? [],
            'runtime' => $manifest['runtime'] ?? [],
            'summary' => $manifest['summary'] ?? [],
            'asset_integrity' => $manifest['asset_integrity'] ?? ['ok' => true, 'missing_count' => 0, 'missing' => []],
            'asset_diagnostics' => $assetDiagnostics,
        ];
    }

    /**
     * Return actionable diagnostics for documentation assets referenced by Opus metadata.
     *
     * @return array<string,mixed>
     */
    public function assetDiagnostics(): array
    {
        return $this->assetDiagnosticsFromManifest($this->repository->load());
    }

    /**
     * Resolve one domain by its public slug.
     *
     * @return array<string,mixed>|null
     */
    public function domainBySlug(string $slug): ?array
    {
        foreach ($this->domains($this->repository->load()) as $domain) {
            if ($domain['slug'] === $slug) {
                return $domain;
            }
        }

        return null;
    }

    /**
     * Resolve one symbol by its stable manifest index.
     *
     * @return array<string,mixed>|null
     */
    public function symbolByIndex(int $index): ?array
    {
        $symbols = $this->symbols($this->repository->load());

        if (!isset($symbols[$index])) {
            return null;
        }

        $symbols[$index]['index'] = $index;

        return $this->withApiCalls($this->withDiagramModels($symbols[$index]));
    }

    /**
     * Resolve one symbol by fully qualified class name.
     *
     * @return array<string,mixed>|null
     */
    public function symbolByName(string $fqcn): ?array
    {
        foreach ($this->symbols($this->repository->load()) as $index => $symbol) {
            if ((string) ($symbol['symbol'] ?? '') === $fqcn) {
                $symbol['index'] = $index;

                return $this->withApiCalls($this->withDiagramModels($symbol));
            }
        }

        return null;
    }

    /**
     * Return all normalized symbols.
     *
     * @return list<array<string,mixed>>
     */
    public function allSymbols(): array
    {
        $symbols = $this->symbols($this->repository->load());

        foreach ($symbols as $index => &$symbol) {
            $symbol['index'] = $index;
        }
        unset($symbol);

        return $symbols;
    }

    /**
     * Attach renderable diagram models to one symbol without changing the source asset.
     *
     * @param array<string,mixed> $symbol
     * @return array<string,mixed>
     */
    /**
     * Attach generated API call examples from the public method signatures.
     *
     * @param array<string,mixed> $symbol
     * @return array<string,mixed>
     */
    private function withApiCalls(array $symbol): array
    {
        $symbol['api_calls'] = $this->apiCallService->forSymbol($symbol);

        return $symbol;
    }
    private function withDiagramModels(array $symbol): array
    {
        $assets = $this->listOfArrays($symbol['diagram_assets'] ?? []);
        $rendered = [];

        foreach ($assets as $asset) {
            $asset['render_model'] = $this->diagramModelBuilder->build($asset);
            $rendered[] = $asset;
        }

        if ($rendered !== []) {
            $symbol['diagram_assets'] = $rendered;
        }

        return $symbol;
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<string,mixed>
     */
    private function assetDiagnosticsFromManifest(array $manifest): array
    {
        $integrity = is_array($manifest['asset_integrity'] ?? null)
            ? $manifest['asset_integrity']
            : ['ok' => true, 'missing_count' => 0, 'missing' => []];

        $missing = $this->listOfArrays($integrity['missing'] ?? []);
        $grouped = [];
        $typeCounts = ['example' => 0, 'diagram' => 0];

        foreach ($missing as $entry) {
            $type = trim((string) ($entry['type'] ?? ''));
            $id = trim((string) ($entry['id'] ?? ''));
            if ($type === '' || $id === '') {
                continue;
            }

            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
            $key = $type . ':' . $id;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'key' => $key,
                    'type' => $type,
                    'id' => $id,
                    'count' => 0,
                    'first_symbol' => '',
                    'first_method' => '',
                    'symbols' => [],
                    'methods' => [],
                ];
            }

            $symbol = trim((string) ($entry['symbol'] ?? ''));
            $method = trim((string) ($entry['method'] ?? ''));

            $grouped[$key]['count']++;
            if ($grouped[$key]['first_symbol'] === '') {
                $grouped[$key]['first_symbol'] = $symbol;
                $grouped[$key]['first_method'] = $method;
            }

            if ($symbol !== '' && !in_array($symbol, $grouped[$key]['symbols'], true)) {
                $grouped[$key]['symbols'][] = $symbol;
            }

            $methodRef = $symbol !== '' && $method !== '' ? $symbol . '::' . $method : $method;
            if ($methodRef !== '' && !in_array($methodRef, $grouped[$key]['methods'], true)) {
                $grouped[$key]['methods'][] = $methodRef;
            }
        }

        $unique = array_values($grouped);
        usort($unique, static function (array $a, array $b): int {
            $typeCompare = strcmp((string) $a['type'], (string) $b['type']);
            if ($typeCompare !== 0) {
                return $typeCompare;
            }

            return strcmp((string) $a['id'], (string) $b['id']);
        });

        return [
            'ok' => (($integrity['ok'] ?? true) === true) && $unique === [],
            'missing_count' => count($missing),
            'unique_missing_count' => count($unique),
            'unique_missing' => $unique,
            'by_type' => $typeCounts,
            'api' => $manifest['api'] ?? [],
            'runtime' => $manifest['runtime'] ?? [],
            'summary' => $manifest['summary'] ?? [],
        ];
    }

    /**
     * Build sorted domain groups with counters for presentation pages.
     *
     * @param array<string,mixed> $manifest
     * @return list<array<string,mixed>>
     */
    private function domains(array $manifest): array
    {
        $grouped = [];

        foreach ($this->symbols($manifest) as $index => $symbol) {
            $name = trim((string) ($symbol['domain'] ?? 'CORE'));
            if ($name === '') {
                $name = 'CORE';
            }

            $symbol['index'] = $index;
            $grouped[$name][] = $symbol;
        }

        $domains = [];

        foreach ($grouped as $name => $domainSymbols) {
            $domains[] = [
                'name' => $name,
                'slug' => $this->slug($name),
                'symbols' => $domainSymbols,
                'count' => count($domainSymbols),
                'method_count' => $this->methodCount($domainSymbols),
                'class_count' => $this->kindCount($domainSymbols, 'class'),
                'interface_count' => $this->kindCount($domainSymbols, 'interface'),
                'description' => $this->content?->domainDescription($name) ?? 'Domaine Opus à documenter depuis les balises source.',
            ];
        }

        usort($domains, static fn(array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        return $domains;
    }

    /**
     * Return the raw source symbols as a list.
     *
     * @param array<string,mixed> $manifest
     * @return list<array<string,mixed>>
     */
    private function symbols(array $manifest): array
    {
        $symbols = array_values(array_filter(
            $manifest['symbols'] ?? [],
            static fn(mixed $symbol): bool => is_array($symbol)
        ));

        foreach ($symbols as &$symbol) {
            $domain = trim((string) ($symbol['domain'] ?? 'CORE'));
            if ($domain === '') {
                $domain = 'CORE';
            }

            $symbol['domain'] = $domain;
            $symbol['domain_slug'] = $this->slug($domain);
            $symbol['display_file'] = $this->portablePath((string) ($symbol['file'] ?? ''), (string) ($manifest['runtime']['opus_root'] ?? ''));
            $symbol = $this->localizeDisplayDocumentation($symbol);
        }
        unset($symbol);

        return $symbols;
    }


    /**
     * Mark source documentation language without translating source metadata.
     *
     * OPUS source metadata is authored in the framework source tags. Until a
     * complete human-reviewed documentation translation catalog exists, RefBook
     * keeps this source text as-is and exposes the language state explicitly.
     *
     * This is not a silent fallback: UI I18N remains selected by the user, while
     * source evidence stays in its declared source language.
     *
     * @param array<string,mixed> $symbol
     * @return array<string,mixed>
     */
    private function localizeDisplayDocumentation(array $symbol): array
    {
        $language = $this->content?->language() ?? ReferenceContentService::DEFAULT_LANGUAGE;

        $symbol['documentation_language'] = ReferenceContentService::DEFAULT_LANGUAGE;
        $symbol['documentation_translation_state'] = $language === ReferenceContentService::DEFAULT_LANGUAGE
            ? 'source'
            : 'source_not_translated';

        return $symbol;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function listOfArrays(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn(mixed $item): bool => is_array($item)));
    }

    /**
     * Count public methods across a domain symbol list.
     *
     * @param list<array<string,mixed>> $symbols
     */
    private function methodCount(array $symbols): int
    {
        $count = 0;

        foreach ($symbols as $symbol) {
            $methods = $symbol['methods'] ?? [];
            if (is_array($methods)) {
                $count += count($methods);
            }
        }

        return $count;
    }

    /**
     * Count symbols matching a manifest kind.
     *
     * @param list<array<string,mixed>> $symbols
     */
    private function kindCount(array $symbols, string $kind): int
    {
        $count = 0;

        foreach ($symbols as $symbol) {
            if ((string) ($symbol['kind'] ?? '') === $kind) {
                $count++;
            }
        }

        return $count;
    }


    /**
     * Convert local absolute paths into public, portable documentation paths.
     */
    private function portablePath(string $path, string $opusRoot): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedRoot = trim(str_replace('\\', '/', $opusRoot), '/');

        if ($normalizedRoot !== '') {
            $rootWithSlash = $normalizedRoot . '/';
            if (str_starts_with(trim($normalizedPath, '/'), $rootWithSlash)) {
                return '<OPUS_ROOT>/' . substr(trim($normalizedPath, '/'), strlen($rootWithSlash));
            }
        }

        $marker = '/framework/Opus/';
        $position = strpos($normalizedPath, $marker);
        if ($position !== false) {
            return '<OPUS_ROOT>' . substr($normalizedPath, $position);
        }

        return $path;
    }

    /**
     * Convert a domain name to its public query slug.
     */
    private function slug(string $value): string
    {
        return trim(strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? ''), '-');
    }
}
