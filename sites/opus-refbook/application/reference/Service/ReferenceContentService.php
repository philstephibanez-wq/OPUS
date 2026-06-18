<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Service;

use RuntimeException;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Load and normalize RefBook I18N content before it reaches ScoreTemplate
 *   representation.
 *
 * Contract:
 *   Structured content only. No routing, no HTTP, no HTML generation, no
 *   template selection. OPUS public identity is ScoreTemplate-based; legacy
 *   Twig wording is not allowed in public-facing RefBook content.
 */
final class ReferenceContentService
{
    public const DEFAULT_LANGUAGE = 'en';

    /** @var list<string> */
    public const SUPPORTED_LANGUAGES = ['fr', 'en', 'es', 'de', 'uk', 'it', 'pl', 'cs'];

    /** @var array<string,mixed>|null */
    private ?array $content = null;

    public function __construct(
        private readonly string $i18nRoot,
        private readonly string $language = self::DEFAULT_LANGUAGE
    ) {
        if (!in_array($this->language, self::SUPPORTED_LANGUAGES, true)) {
            throw new RuntimeException('OPUS_REFBOOK_LANG_UNSUPPORTED=' . $this->language);
        }
    }

    public function language(): string
    {
        return $this->language;
    }

    /** @return list<string> */
    public function supportedLanguages(): array
    {
        return self::SUPPORTED_LANGUAGES;
    }

    /** @return list<array{code:string,label:string,state:string,partial:bool,badge:string,source_language:string}> */
    public function languageOptions(): array
    {
        return array_map(
            fn(array $option): array => $option + $this->languageState($option['code']),
            [
                ['code' => 'fr', 'label' => 'Français'],
                ['code' => 'en', 'label' => 'English'],
                ['code' => 'es', 'label' => 'Español'],
                ['code' => 'de', 'label' => 'Deutsch'],
                ['code' => 'uk', 'label' => 'Українська'],
                ['code' => 'it', 'label' => 'Italiano'],
                ['code' => 'pl', 'label' => 'Polski'],
                ['code' => 'cs', 'label' => 'Čeština'],
            ]
        );
    }

    /** @return list<string> */
    public function completeLanguages(): array
    {
        return self::SUPPORTED_LANGUAGES;
    }

    /** @return list<string> */
    public function partialLanguages(): array
    {
        return [];
    }

    public function isPartialLanguage(?string $language = null): bool
    {
        return in_array($language ?? $this->language, $this->partialLanguages(), true);
    }

    /** @return array{code:string,state:string,complete:bool,partial:bool,source_language:string,badge:string} */
    public function languageState(?string $language = null): array
    {
        $code = $language ?? $this->language;
        if (!in_array($code, self::SUPPORTED_LANGUAGES, true)) {
            throw new RuntimeException('OPUS_REFBOOK_LANG_UNSUPPORTED=' . $code);
        }

        $partial = $this->isPartialLanguage($code);

        return [
            'code' => $code,
            'state' => $partial ? 'partial' : 'complete',
            'complete' => !$partial,
            'partial' => $partial,
            'source_language' => $code,
            'badge' => $partial ? '[partial]' : '',
        ];
    }

    public function contentSourceLanguage(): string
    {
        return $this->languageState()['source_language'];
    }

    /** @return array<string,mixed> */
    public function labels(): array
    {
        $labels = $this->load()['labels'] ?? [];
        if (!is_array($labels)) {
            $labels = [];
        }

        return array_replace_recursive($this->templateUiFallbackLabels(), $labels);
    }

    public function t(string $key): string
    {
        $value = $this->dot($this->labels(), $key);
        if (is_string($value)) {
            return $value;
        }
        return '⚠[' . $key . ']';
    }

    /** @return array<string,mixed> */
    public function module(): array
    {
        $module = $this->load()['module'] ?? [];
        return is_array($module) ? $module : [];
    }

    /** @return list<array<string,mixed>> */
    public function guides(): array
    {
        return array_values(array_filter(
            $this->load()['guides'] ?? [],
            static fn(mixed $page): bool => is_array($page)
        ));
    }

    /** @return list<array<string,string>> */
    public function guideNavigation(): array
    {
        return array_map(
            static fn(array $page): array => [
                'slug' => (string) ($page['slug'] ?? ''),
                'title' => (string) ($page['title'] ?? ''),
            ],
            $this->guides()
        );
    }

    /** @return array<string,mixed>|null */
    public function guideBySlug(string $slug): ?array
    {
        foreach ($this->guides() as $page) {
            if ((string) ($page['slug'] ?? '') === $slug) {
                return $page;
            }
        }
        return null;
    }

    /** @return list<array<string,string>> */
    public function homeCards(): array
    {
        return array_values(array_filter(
            $this->load()['home_cards'] ?? [],
            static fn(mixed $card): bool => is_array($card)
        ));
    }

    public function domainDescription(string $domain): string
    {
        $domain = trim($domain);
        $descriptions = $this->load()['domain_descriptions'] ?? [];
        if (is_array($descriptions) && isset($descriptions[$domain]) && is_string($descriptions[$domain])) {
            return $descriptions[$domain];
        }

        return $this->undocumentedDomainDescription($domain);
    }

    private function undocumentedDomainDescription(string $domain): string
    {
        $domain = $domain !== '' ? $domain : 'UNCLASSIFIED';

        $messages = [
            'fr' => [
                'RefBook' => 'Surface documentaire RefBook et catalogue public du framework Opus.',
                'UNCLASSIFIED' => 'Symboles détectés par le manifeste sans domaine éditorial attribué.',
                'default' => 'Domaine Opus détecté dans le manifeste sans description éditoriale dédiée : %s.',
            ],
            'en' => [
                'RefBook' => 'RefBook documentation surface and public catalog for the Opus framework.',
                'UNCLASSIFIED' => 'Manifest-detected symbols without an assigned editorial domain.',
                'default' => 'Opus domain detected in the manifest without a dedicated editorial description: %s.',
            ],
            'es' => [
                'RefBook' => 'Superficie documental RefBook y catálogo público del framework Opus.',
                'UNCLASSIFIED' => 'Símbolos detectados por el manifiesto sin dominio editorial asignado.',
                'default' => 'Dominio Opus detectado en el manifiesto sin descripción editorial dedicada: %s.',
            ],
            'de' => [
                'RefBook' => 'RefBook-Dokumentationsoberfläche und öffentlicher Katalog des Opus-Frameworks.',
                'UNCLASSIFIED' => 'Vom Manifest erkannte Symbole ohne zugewiesene redaktionelle Domäne.',
                'default' => 'Opus-Domäne im Manifest erkannt, ohne dedizierte redaktionelle Beschreibung: %s.',
            ],
            'uk' => [
                'RefBook' => 'Документаційна поверхня RefBook та публічний каталог фреймворку Opus.',
                'UNCLASSIFIED' => 'Символи, виявлені маніфестом, без призначеного редакційного домену.',
                'default' => 'Домен Opus виявлено в маніфесті без окремого редакційного опису: %s.',
            ],
            'it' => [
                'RefBook' => 'Superficie documentale RefBook e catalogo pubblico del framework Opus.',
                'UNCLASSIFIED' => 'Simboli rilevati dal manifesto senza un dominio editoriale assegnato.',
                'default' => 'Dominio Opus rilevato nel manifesto senza descrizione editoriale dedicata: %s.',
            ],
            'pl' => [
                'RefBook' => 'Powierzchnia dokumentacyjna RefBook i publiczny katalog frameworka Opus.',
                'UNCLASSIFIED' => 'Symbole wykryte w manifeście bez przypisanej domeny redakcyjnej.',
                'default' => 'Domena Opus wykryta w manifeście bez dedykowanego opisu redakcyjnego: %s.',
            ],
            'cs' => [
                'RefBook' => 'Dokumentační plocha RefBook a veřejný katalog frameworku Opus.',
                'UNCLASSIFIED' => 'Symboly nalezené v manifestu bez přiřazené redakční domény.',
                'default' => 'Doména Opus nalezená v manifestu bez vyhrazeného redakčního popisu: %s.',
            ],
        ];

        $languageMessages = $messages[$this->language] ?? $messages[self::DEFAULT_LANGUAGE];
        if (isset($languageMessages[$domain])) {
            return $languageMessages[$domain];
        }

        return sprintf($languageMessages['default'], $domain);
    }

    /** @return array<string,mixed> */
    private function load(): array
    {
        if ($this->content !== null) {
            return $this->content;
        }

        $sourceLanguage = $this->contentSourceLanguage();
        $file = rtrim($this->i18nRoot, '/\\') . DIRECTORY_SEPARATOR . $sourceLanguage . '.json';
        if (!is_file($file)) {
            throw new RuntimeException('OPUS_REFBOOK_I18N_FILE_MISSING=' . $file);
        }

        $json = json_decode((string) file_get_contents($file), true);
        if (!is_array($json)) {
            throw new RuntimeException('OPUS_REFBOOK_I18N_JSON_INVALID=' . $file);
        }
        if (($json['schema'] ?? null) !== 'OPUS_REFBOOK_I18N_V1') {
            throw new RuntimeException('OPUS_REFBOOK_I18N_SCHEMA_INVALID=' . (string) ($json['schema'] ?? 'missing'));
        }
        if (($json['language'] ?? null) !== $sourceLanguage) {
            throw new RuntimeException('OPUS_REFBOOK_I18N_LANGUAGE_MISMATCH=' . $file);
        }

        $this->content = $this->sanitizePublicText($json);
        return $this->content;
    }

    /**
     * Hide local workstation absolute paths and obsolete renderer wording from
     * public documentation content.
     *
     * @param mixed $value
     * @return mixed
     */
    private function sanitizePublicText(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->sanitizePublicText($item);
            }
            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }

        $value = str_replace(['H:\\Opus', 'H:/OPUS', 'H:\OPUS'], '<OPUS_ROOT>', $value);
        $value = preg_replace('~[A-Za-z]:[\\/]+Opus(?=[\\/\s\.,]|$)~', '<OPUS_ROOT>', $value) ?? $value;
        $value = str_replace('\\', '/', $value);

        return str_replace(
            [
                'TwigTemplateRenderer',
                'clean Twig',
                'Twig as data storage',
                'Twig must not invent it',
                'Twig renders',
                'Twig turns the view into HTML',
                'Twig…',
                'Twig',
            ],
            [
                'ScoreTemplateRenderer',
                'clean ScoreTemplate',
                'templates as data storage',
                'templates must not invent it',
                'ScoreTemplate renders',
                'ScoreTemplate turns the view into HTML',
                'ScoreTemplate…',
                'ScoreTemplate',
            ],
            $value
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function templateUiFallbackLabels(): array
    {
        $paths = [
            'language.current', 'language.fr', 'language.en', 'language.es', 'language.de', 'language.uk', 'language.it', 'language.pl', 'language.cs', 'language.apply',
            'sidebar.api_reference', 'sidebar.guides', 'sidebar.domains', 'sidebar.menu', 'sidebar.search', 'sidebar.assets_docs', 'sidebar.legal', 'sidebar.download_install',
            'home.kicker', 'home.symbols', 'home.domains', 'home.pipeline_title', 'home.guides_title',
            'api.title', 'api.kicker', 'api.intro', 'api.how_to_read', 'api.symbols',
            'domain.kicker', 'domain.symbols', 'domain.with_methods', 'domain.table_symbol', 'domain.table_role', 'domain.table_methods', 'domain.table_source', 'domain.metrics_label', 'domain.public_methods', 'domain.classes', 'domain.interfaces', 'domain.inventory_kicker', 'domain.inventory_title',
            'symbol.fallback_kind', 'symbol.fallback_name', 'symbol.fallback_role', 'symbol.contract_title', 'symbol.no_contract', 'symbol.source_title', 'symbol.source_missing', 'symbol.public_methods_title', 'symbol.no_methods', 'symbol.table_method', 'symbol.table_signature', 'symbol.identity_kicker', 'symbol.identity_title', 'symbol.kind_label', 'symbol.domain_label', 'symbol.namespace_label', 'symbol.namespace_missing', 'symbol.methods_kicker', 'symbol.examples_title', 'symbol.diagrams_title',
            'symbol_extra.responsibility', 'symbol_extra.role', 'symbol_extra.declared_examples_missing', 'symbol_extra.declared_diagrams_missing',
            'legal.kicker', 'legal.title', 'legal.intro', 'legal.copyright_kicker', 'legal.author_title', 'legal.original_author', 'legal.licensing_kicker', 'legal.licensing_title', 'legal.personal_free', 'legal.commercial_required', 'legal.rights_reserved', 'legal.note', 'legal.distribution_kicker', 'legal.distribution_title', 'legal.distribution_body_1', 'legal.distribution_body_2',
            'not_found.kicker', 'not_found.title', 'not_found.message',
            'topbar.subtitle', 'topbar.controls',
            'breadcrumb.label', 'breadcrumb.home',
            'guide.reading_kicker', 'guide.reading_title', 'guide.sections',
            'theme.current', 'theme.night', 'theme.ocean', 'theme.paper', 'theme.short.night', 'theme.short.ocean', 'theme.short.paper',
            'search.kicker', 'search.title', 'search.intro', 'search.form_label', 'search.input_label', 'search.placeholder', 'search.placeholder_long', 'search.submit', 'search.help', 'search.results_kicker', 'search.results_title', 'search.results', 'search.empty_title', 'search.empty_body', 'search.tips_kicker', 'search.tips_title', 'search.type_guide', 'search.type_domain', 'search.type_symbol', 'search.symbols', 'search.methods', 'search.snippet_default',
            'assets.kicker', 'assets.title', 'assets.intro', 'assets.snapshot_state', 'assets.complete', 'assets.incomplete', 'assets.asset_count', 'assets.missing_refs', 'assets.unique_missing', 'assets.example_refs', 'assets.diagram_refs', 'assets.truth_source', 'assets.truth_suffix', 'assets.correction_rule', 'assets.create_examples', 'assets.create_diagrams', 'assets.no_placeholder', 'assets.rerun_smoke', 'assets.inventory_kicker', 'assets.inventory_title', 'assets.none_missing', 'assets.references', 'assets.first_usage', 'assets.useful_endpoints', 'assets.short_warning', 'assets.see_diagnostic', 'assets.type', 'assets.id', 'assets.top_limit',
            'runtime.kicker', 'runtime.title', 'runtime.source', 'runtime.opus_root', 'runtime.read_only', 'runtime.api', 'runtime.yes', 'runtime.no', 'runtime.endpoints_title', 'runtime.method', 'runtime.path', 'runtime.description', 'runtime.asset_warning_title', 'runtime.asset_warning_body',
            'diagram.aria', 'diagram.source', 'diagram.renderer_unavailable',
            'footer.powered_by', 'footer.copyright', 'footer.legal_short', 'footer.license_summary',
            'seo.description_default', 'seo.description_legal', 'seo.description_api_reference', 'seo.description_download_install', 'seo.description_asset_diagnostics', 'seo.description_search', 'seo.description_domain_suffix', 'seo.description_symbol_default',
        ];

        $fallback = [];
        foreach ($paths as $path) {
            $this->setFallbackLabel($fallback, $path, '⚠[' . $path . ']');
        }

        return $fallback;
    }

    /**
     * @param array<string,mixed> $target
     */
    private function setFallbackLabel(array &$target, string $path, string $value): void
    {
        $cursor =& $target;
        foreach (explode('.', $path) as $segment) {
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }
            $cursor =& $cursor[$segment];
        }
        $cursor = $value;
    }

    private function dot(array $data, string $path): mixed
    {
        $cursor = $data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }
            $cursor = $cursor[$segment];
        }
        return $cursor;
    }
}
