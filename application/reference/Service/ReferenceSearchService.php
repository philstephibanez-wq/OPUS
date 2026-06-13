<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Service;

use RuntimeException;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Build the server-side RefBook search results from official RefBook data.
 *
 * Responsibility:
 *   Search guides, domains and source symbols without touching routing,
 *   rendering, HTTP state or framework internals.
 *
 * Arguments:
 *   - $catalog: official catalog built from the generated Opus manifest.
 *   - $content: official localized RefBook content provider.
 *
 * Returns:
 *   - search(): normalized query metadata and ranked result rows.
 *
 * Side effects:
 *   None. Reads only through injected services.
 *
 * Errors:
 *   Throws RuntimeException OPUS_REFBOOK_SEARCH_QUERY_TOO_LONG when an
 *   explicit query exceeds the public contract length.
 *
 * Contract:
 *   No external index, no database, no framework patch, no silent data source.
 *   Empty query is a valid UI state and returns no results.
 *
 * Version:
 *   P113B8_REFBOOK_SEARCH
 */
final class ReferenceSearchService
{
    public const MAX_QUERY_LENGTH = 96;
    public const DEFAULT_LIMIT = 60;

    public function __construct(
        private readonly ReferenceCatalogService $catalog,
        private readonly ReferenceContentService $content
    ) {
    }

    /**
     * Search RefBook guides, domains and symbols.
     *
     * @return array{query:string,has_query:bool,count:int,results:list<array<string,mixed>>}
     */
    public function search(string $query, int $limit = self::DEFAULT_LIMIT): array
    {
        $query = $this->normalizeQuery($query);

        if ($query === '') {
            return [
                'query' => '',
                'has_query' => false,
                'count' => 0,
                'results' => [],
            ];
        }

        $terms = $this->terms($query);
        $results = [];

        foreach ($this->guideRows() as $row) {
            $match = $this->match($row['haystack'], $terms, $query);
            if ($match['score'] > 0) {
                $results[] = $this->resultRow($row, $match);
            }
        }

        foreach ($this->domainRows() as $row) {
            $match = $this->match($row['haystack'], $terms, $query);
            if ($match['score'] > 0) {
                $results[] = $this->resultRow($row, $match);
            }
        }

        foreach ($this->symbolRows() as $row) {
            $match = $this->match($row['haystack'], $terms, $query);
            if ($match['score'] > 0) {
                $results[] = $this->resultRow($row, $match);
            }
        }

        usort($results, static function (array $a, array $b): int {
            $score = ($b['score'] <=> $a['score']);
            if ($score !== 0) {
                return $score;
            }

            return strcmp((string) $a['title'], (string) $b['title']);
        });

        $results = array_slice($results, 0, max(1, $limit));

        return [
            'query' => $query,
            'has_query' => true,
            'count' => count($results),
            'results' => $results,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function guideRows(): array
    {
        $rows = [];

        foreach ($this->content->guides() as $guide) {
            $sections = $guide['sections'] ?? [];
            $items = [];
            if (is_array($sections)) {
                foreach ($sections as $section) {
                    if (is_array($section)) {
                        $items[] = (string) ($section['title'] ?? '');
                        foreach (($section['items'] ?? []) as $item) {
                            $items[] = (string) $item;
                        }
                    }
                }
            }

            $rows[] = [
                'type' => 'guide',
                'page' => (string) ($guide['slug'] ?? ''),
                'title' => (string) ($guide['title'] ?? ''),
                'subtitle' => (string) ($guide['summary'] ?? ''),
                'meta' => (string) ($guide['kicker'] ?? $this->content->t('search.type_guide')),
                'badges' => [$this->content->t('search.type_guide')],
                'haystack' => [
                    (string) ($guide['slug'] ?? ''),
                    (string) ($guide['kicker'] ?? ''),
                    (string) ($guide['title'] ?? ''),
                    (string) ($guide['summary'] ?? ''),
                    (string) ($guide['reading'] ?? ''),
                    implode(' ', $items),
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function domainRows(): array
    {
        $rows = [];

        foreach ($this->catalog->overview()['domains'] as $domain) {
            $rows[] = [
                'type' => 'domain',
                'page' => 'domain-' . (string) ($domain['slug'] ?? ''),
                'title' => (string) ($domain['name'] ?? 'DOMAIN'),
                'subtitle' => (string) ($domain['description'] ?? ''),
                'meta' => (string) ($domain['count'] ?? 0) . ' ' . $this->content->t('search.symbols'),
                'badges' => [$this->content->t('search.type_domain')],
                'haystack' => [
                    (string) ($domain['name'] ?? ''),
                    (string) ($domain['slug'] ?? ''),
                    (string) ($domain['description'] ?? ''),
                ],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function symbolRows(): array
    {
        $rows = [];

        foreach ($this->catalog->overview()['domains'] as $domain) {
            foreach (($domain['symbols'] ?? []) as $symbol) {
                if (!is_array($symbol)) {
                    continue;
                }

                $methods = $this->methodTexts($symbol['methods'] ?? []);
                $contract = $this->stringList($symbol['contract'] ?? []);
                $examples = $this->stringList($symbol['examples'] ?? []);
                $diagrams = $this->stringList($symbol['diagrams'] ?? []);
                $kind = (string) ($symbol['kind'] ?? $this->content->t('symbol.fallback_kind'));
                $domainName = (string) ($symbol['domain'] ?? $domain['name'] ?? 'CORE');
                $methodCount = count($methods);

                $rows[] = [
                    'type' => 'symbol',
                    'page' => 'symbol-' . (string) ($symbol['index'] ?? 0),
                    'title' => (string) ($symbol['symbol'] ?? $symbol['name'] ?? $this->content->t('symbol.fallback_name')),
                    'subtitle' => (string) ($symbol['role'] ?? $this->content->t('symbol.fallback_role')),
                    'meta' => $domainName . ' Â· ' . $kind . ' Â· ' . $methodCount . ' ' . $this->content->t('search.methods'),
                    'badges' => [$this->content->t('search.type_symbol'), $domainName, $kind],
                    'haystack' => [
                        (string) ($symbol['symbol'] ?? ''),
                        (string) ($symbol['name'] ?? ''),
                        (string) ($symbol['namespace'] ?? ''),
                        (string) ($symbol['file'] ?? ''),
                        (string) ($symbol['domain'] ?? ''),
                        (string) ($symbol['kind'] ?? ''),
                        (string) ($symbol['role'] ?? ''),
                        implode(' ', $methods),
                        implode(' ', $contract),
                        implode(' ', $examples),
                        implode(' ', $diagrams),
                    ],
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     * @param array{score:int,snippet:string} $match
     * @return array<string,mixed>
     */
    private function resultRow(array $row, array $match): array
    {
        unset($row['haystack']);

        $row['score'] = $match['score'];
        $row['snippet'] = $match['snippet'];

        return $row;
    }

    /**
     * @param list<string> $haystack
     * @param list<string> $terms
     * @return array{score:int,snippet:string}
     */
    private function match(array $haystack, array $terms, string $query): array
    {
        $score = 0;
        $snippet = '';
        $normalizedQuery = $this->normalizeText($query);

        foreach ($haystack as $field) {
            $field = trim($field);
            if ($field === '') {
                continue;
            }

            $normalizedField = $this->normalizeText($field);

            if ($normalizedField === $normalizedQuery) {
                $score += 120;
                $snippet = $this->chooseSnippet($snippet, $field);
            } elseif (str_starts_with($normalizedField, $normalizedQuery)) {
                $score += 70;
                $snippet = $this->chooseSnippet($snippet, $field);
            } elseif (str_contains($normalizedField, $normalizedQuery)) {
                $score += 38;
                $snippet = $this->chooseSnippet($snippet, $field);
            }

            foreach ($terms as $term) {
                if ($term !== '' && str_contains($normalizedField, $term)) {
                    $score += 12;
                    $snippet = $this->chooseSnippet($snippet, $field);
                }
            }
        }

        return [
            'score' => $score,
            'snippet' => $this->trimSnippet($snippet),
        ];
    }

    /**
     * @return list<string>
     */
    private function terms(string $query): array
    {
        $parts = preg_split('/\s+/', $this->normalizeText($query)) ?: [];

        return array_values(array_unique(array_filter(
            $parts,
            static fn(string $term): bool => strlen($term) >= 2
        )));
    }

    private function normalizeQuery(string $query): string
    {
        $query = trim(preg_replace('/\s+/', ' ', $query) ?? '');

        if (strlen($query) > self::MAX_QUERY_LENGTH) {
            throw new RuntimeException('OPUS_REFBOOK_SEARCH_QUERY_TOO_LONG=' . strlen($query));
        }

        return $query;
    }

    private function normalizeText(string $value): string
    {
        return strtolower(trim($value));
    }

    private function chooseSnippet(string $current, string $candidate): string
    {
        if ($current === '') {
            return $candidate;
        }

        return strlen($candidate) < strlen($current) ? $candidate : $current;
    }

    private function trimSnippet(string $snippet): string
    {
        $snippet = trim($snippet);

        if ($snippet === '') {
            return $this->content->t('search.snippet_default');
        }

        if (strlen($snippet) <= 180) {
            return $snippet;
        }

        return substr($snippet, 0, 177) . 'â€¦';
    }

    /**
     * @param mixed $methods
     * @return list<string>
     */
    private function methodTexts(mixed $methods): array
    {
        if (!is_array($methods)) {
            return [];
        }

        $texts = [];
        foreach ($methods as $method) {
            if (!is_array($method)) {
                continue;
            }

            $texts[] = trim((string) ($method['name'] ?? '') . ' ' . (string) ($method['signature'] ?? ''));
        }

        return array_values(array_filter($texts));
    }

    /**
     * @param mixed $items
     * @return list<string>
     */
    private function stringList(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn(mixed $item): string => (string) $item, $items)));
    }
}
