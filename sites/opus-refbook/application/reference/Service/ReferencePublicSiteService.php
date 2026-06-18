<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Service;

use RuntimeException;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Prepare public visibility, canonical URL, SEO metadata and discovery documents.
 *
 * Contract:
 *   Data preparation only. No HTTP emission, no Twig rendering, no routing decision.
 */
final class ReferencePublicSiteService
{
    private const DEFAULT_PUBLIC_BASE_URL = 'https://opus.logandplay.org';
    private const DEFAULT_LOCAL_BASE_PATH = '/';

    public function publicBaseUrl(): string
    {
        $raw = $_ENV['OPUS_REFBOOK_PUBLIC_BASE_URL'] ?? getenv('OPUS_REFBOOK_PUBLIC_BASE_URL');
        if ($raw === false || trim((string) $raw) === '') {
            $raw = self::DEFAULT_PUBLIC_BASE_URL;
        }

        $value = rtrim(trim((string) $raw), '/');

        if (!str_starts_with($value, 'https://') && !str_starts_with($value, 'http://')) {
            throw new RuntimeException('OPUS_REFBOOK_PUBLIC_BASE_URL_INVALID=' . $value);
        }

        return $value;
    }

    public function basePath(): string
    {
        $raw = $_ENV['OPUS_REFBOOK_BASE_PATH'] ?? getenv('OPUS_REFBOOK_BASE_PATH');
        if ($raw === false || trim((string) $raw) === '') {
            $raw = self::DEFAULT_LOCAL_BASE_PATH;
        }

        $value = trim((string) $raw);

        if ($value === '/') {
            return '';
        }

        if ($value !== '' && !str_starts_with($value, '/')) {
            throw new RuntimeException('OPUS_REFBOOK_BASE_PATH_INVALID=' . $value);
        }

        return rtrim($value, '/');
    }

    /**
     * @return array<string,string>
     */
    public function branding(array $footer = []): array
    {
        return [
            'copyright' => (string) ($footer['copyright'] ?? '⚠[footer.copyright]'),
            'powered_by' => (string) ($footer['powered_by'] ?? '⚠[footer.powered_by]'),
            'author' => 'Steve Ibanez',
            'brand' => 'Opus Framework',
            'publisher' => 'Log&Play',
            'legal_short' => (string) ($footer['legal_short'] ?? '⚠[footer.legal_short]'),
            'license_summary' => (string) ($footer['license_summary'] ?? '⚠[footer.license_summary]'),
        ];
    }

    /**
     * @param array<string,mixed> $viewData
     * @return array<string,mixed>
     */
    public function seo(array $viewData): array
    {
        $pageSlug = (string) ($viewData['pageSlug'] ?? '');
        $language = (string) ($viewData['lang'] ?? ReferenceContentService::DEFAULT_LANGUAGE);
        $title = trim((string) ($viewData['title'] ?? 'Opus Reference Book'));
        $moduleTitle = trim((string) ($viewData['moduleTitle'] ?? 'Opus Reference Book'));
        $fullTitle = $title === $moduleTitle ? $title : $title . ' — ' . $moduleTitle;
        $description = $this->description($pageSlug, $viewData);
        $robots = $this->robots($pageSlug);
        $canonical = $this->canonicalUrl($pageSlug, $language);

        return [
            'title' => $fullTitle,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $robots,
            'og_type' => $pageSlug === '' ? 'website' : 'article',
            'og_site_name' => 'Opus Reference Book',
            'og_url' => $canonical,
            'og_title' => $fullTitle,
            'og_description' => $description,
            'twitter_card' => 'summary',
            'json_ld' => $this->jsonLd($pageSlug, $language, $fullTitle, $description, $canonical),
        ];
    }

    public function canonicalUrl(string $pageSlug = '', string $language = ReferenceContentService::DEFAULT_LANGUAGE): string
    {
        $publicBaseUrl = $this->publicBaseUrl();
        $path = $this->canonicalPath($pageSlug);
        $url = $publicBaseUrl . $path;

        if ($language !== ReferenceContentService::DEFAULT_LANGUAGE) {
            $url .= '?lang=' . rawurlencode($language);
        }

        return $url;
    }

    public function robotsTxt(): string
    {
        return implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /api/',
            'Disallow: /api/refbook/',
            'Disallow: /asset-diagnostics',
            'Disallow: /search',
            '',
            'Sitemap: ' . $this->publicBaseUrl() . '/sitemap.xml',
            '',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $symbols
     * @param list<array<string,string>> $guides
     * @param list<array<string,mixed>> $domains
     */
    public function sitemapXml(array $symbols, array $guides, array $domains): string
    {
        $entries = [];
        $entries[] = ['slug' => '', 'changefreq' => 'weekly', 'priority' => '1.0'];
        $entries[] = ['slug' => 'api-reference', 'changefreq' => 'weekly', 'priority' => '0.8'];
        $entries[] = ['slug' => 'legal', 'changefreq' => 'monthly', 'priority' => '0.7'];

        foreach ($guides as $guide) {
            $slug = (string) ($guide['slug'] ?? '');
            if ($slug !== '') {
                $entries[] = ['slug' => $slug, 'changefreq' => 'weekly', 'priority' => '0.7'];
            }
        }

        foreach ($domains as $domain) {
            $slug = (string) ($domain['slug'] ?? '');
            if ($slug !== '') {
                $entries[] = ['slug' => 'domain-' . $slug, 'changefreq' => 'weekly', 'priority' => '0.7'];
            }
        }

        foreach ($symbols as $symbol) {
            $index = (string) ($symbol['index'] ?? '');
            if ($index !== '') {
                $entries[] = ['slug' => 'symbol-' . $index, 'changefreq' => 'weekly', 'priority' => '0.6'];
            }
        }

        $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

        foreach ($entries as $entry) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>' . $this->xml($this->canonicalUrl((string) $entry['slug'])) . '</loc>';
            $lines[] = '    <changefreq>' . $this->xml((string) $entry['changefreq']) . '</changefreq>';
            $lines[] = '    <priority>' . $this->xml((string) $entry['priority']) . '</priority>';
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param array<string,mixed> $viewData
     */
    private function description(string $pageSlug, array $viewData): string
    {
        $seo = [];
        if (isset($viewData['ui']) && is_array($viewData['ui']) && isset($viewData['ui']['seo']) && is_array($viewData['ui']['seo'])) {
            $seo = $viewData['ui']['seo'];
        }

        if (isset($viewData['symbol']) && is_array($viewData['symbol'])) {
            $symbol = $viewData['symbol'];
            $name = (string) ($symbol['symbol'] ?? $symbol['name'] ?? 'Opus class');
            $role = trim((string) ($symbol['role'] ?? $symbol['responsibility'] ?? ''));
            $fallback = (string) ($seo['description_symbol_default'] ?? '⚠[seo.description_symbol_default]');
            return $role !== ''
                ? $name . ' — ' . $role
                : $name . ' ' . $fallback;
        }

        if (isset($viewData['domain']) && is_array($viewData['domain'])) {
            $domain = $viewData['domain'];
            $suffix = (string) ($seo['description_domain_suffix'] ?? '⚠[seo.description_domain_suffix]');
            return 'Opus Framework ' . (string) ($domain['name'] ?? 'domain') . ' ' . $suffix;
        }

        $map = [
            '' => 'description_default',
            'legal' => 'description_legal',
            'api-reference' => 'description_api_reference',
            'download-install' => 'description_download_install',
            'asset-diagnostics' => 'description_asset_diagnostics',
            'search' => 'description_search',
        ];

        $key = $map[$pageSlug] ?? 'description_default';
        return (string) ($seo[$key] ?? '⚠[seo.' . $key . ']');
    }

    private function robots(string $pageSlug): string
    {
        if ($pageSlug === 'search' || $pageSlug === 'asset-diagnostics') {
            return 'noindex,follow';
        }

        return 'index,follow,max-image-preview:large';
    }

    private function canonicalPath(string $pageSlug): string
    {
        $pageSlug = trim($pageSlug);

        if ($pageSlug === '') {
            return '/';
        }

        return '/' . rawurlencode($pageSlug);
    }

    private function jsonLd(string $pageSlug, string $language, string $title, string $description, string $canonical): string
    {
        $branding = $this->branding();
        $type = $pageSlug === '' ? 'WebSite' : 'TechArticle';

        $payload = [
            '@context' => 'https://schema.org',
            '@type' => $type,
            'name' => $title,
            'description' => $description,
            'url' => $canonical,
            'inLanguage' => $language,
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => 'Opus Reference Book',
                'url' => $this->publicBaseUrl() . '/',
            ],
            'creator' => [
                '@type' => 'Person',
                'name' => $branding['author'],
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $branding['publisher'],
            ],
            'copyrightHolder' => [
                '@type' => 'Person',
                'name' => $branding['author'],
            ],
            'copyrightYear' => 2026,
        ];

        if ($pageSlug === '') {
            $payload['about'] = [
                '@type' => 'SoftwareSourceCode',
                'name' => 'Opus Framework',
                'programmingLanguage' => 'PHP',
                'runtimePlatform' => 'PHP 8',
                'license' => $this->publicBaseUrl() . '/legal',
            ];
        }

        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
