<?php
declare(strict_types=1);

namespace Opus\Console\Command;

use Opus\Console\OpusConsoleException;

/**
 * Adds a locale to an existing OPUS site without recreating the site.
 *
 * Contract:
 * - patches an existing site incrementally;
 * - never recreates or overwrites the site scaffold;
 * - refuses an existing locale unless a future explicit migration command is added;
 * - writes i18n and starter content files only for the requested locale;
 * - updates application/config/site.json after all new files are ready;
 * - no external dependency.
 */
final class AddLanguageCommand implements OpusConsoleCommandInterface
{
    public function __construct(private readonly string $opusRoot)
    {
    }

    public function name(): string
    {
        return 'add:language';
    }

    /**
     * @param list<string> $arguments
     */
    public function run(array $arguments): int
    {
        [$positionals, $write] = $this->parseArguments($arguments);

        $siteId = (string)($positionals[0] ?? '');
        $locale = strtolower((string)($positionals[1] ?? ''));

        if ($siteId === '') {
            throw new OpusConsoleException('OPUS_ADD_LANGUAGE_MISSING_SITE_ID');
        }

        if ($locale === '') {
            throw new OpusConsoleException('OPUS_ADD_LANGUAGE_MISSING_LOCALE');
        }

        if (!preg_match('/^[a-z][a-z0-9-]*$/', $siteId)) {
            throw new OpusConsoleException('OPUS_ADD_LANGUAGE_INVALID_SITE_ID: ' . $siteId);
        }

        if (!preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $locale)) {
            throw new OpusConsoleException('OPUS_ADD_LANGUAGE_INVALID_LOCALE: ' . $locale);
        }

        $siteRoot = $this->absolutePath('sites/' . $siteId);
        if (!is_dir($siteRoot)) {
            throw new OpusConsoleException('OPUS_ADD_LANGUAGE_SITE_NOT_FOUND: sites/' . $siteId);
        }

        $siteConfigPath = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json';
        $routesPath = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes.json';

        $siteConfig = $this->readJson($siteConfigPath, 'OPUS_ADD_LANGUAGE_SITE_JSON_INVALID');
        $routesConfig = $this->readJson($routesPath, 'OPUS_ADD_LANGUAGE_ROUTES_JSON_INVALID');

        $defaultLocale = (string)($siteConfig['default_locale'] ?? 'fr');
        $locales = $siteConfig['locales'] ?? [];
        if (!is_array($locales)) {
            throw new OpusConsoleException('OPUS_ADD_LANGUAGE_LOCALES_CONTRACT_INVALID');
        }

        if (in_array($locale, $locales, true)) {
            throw new OpusConsoleException('OPUS_ADD_LANGUAGE_ALREADY_REGISTERED: ' . $locale);
        }

        if (!in_array($defaultLocale, $locales, true)) {
            throw new OpusConsoleException('OPUS_ADD_LANGUAGE_DEFAULT_LOCALE_NOT_REGISTERED: ' . $defaultLocale);
        }

        $plannedFiles = $this->plannedFiles($siteId, $locale, $defaultLocale, $siteRoot, $routesConfig);

        foreach (array_keys($plannedFiles) as $absolutePath) {
            if (file_exists($absolutePath)) {
                throw new OpusConsoleException('OPUS_ADD_LANGUAGE_TARGET_ALREADY_EXISTS: ' . $this->relativeDisplayPath($absolutePath));
            }
        }

        if (!$write) {
            echo "OPUS_ADD_LANGUAGE_DRY_RUN\n";
            foreach (array_keys($plannedFiles) as $absolutePath) {
                echo '[FILE] ' . $this->relativeDisplayPath($absolutePath) . "\n";
            }
            echo '[PATCH] sites/' . $siteId . "/application/config/site.json locales += {$locale}\n";
            echo "Run again with --write to add the language.\n";
            return 0;
        }

        foreach ($plannedFiles as $absolutePath => $content) {
            $parent = dirname($absolutePath);
            if (!is_dir($parent) && !mkdir($parent, 0775, true)) {
                throw new OpusConsoleException('OPUS_ADD_LANGUAGE_DIRECTORY_CREATE_FAILED: ' . $this->relativeDisplayPath($parent));
            }

            if (file_put_contents($absolutePath, $content) === false) {
                throw new OpusConsoleException('OPUS_ADD_LANGUAGE_FILE_WRITE_FAILED: ' . $this->relativeDisplayPath($absolutePath));
            }
        }

        $locales[] = $locale;
        $locales = array_values(array_unique($locales));
        sort($locales);
        $siteConfig['locales'] = $locales;

        $this->writeJson($siteConfigPath, $siteConfig, 'OPUS_ADD_LANGUAGE_SITE_JSON_WRITE_FAILED');

        echo "OPUS_ADD_LANGUAGE_WRITTEN: {$siteId}/{$locale}\n";
        return 0;
    }

    /**
     * @param list<string> $arguments
     * @return array{0:list<string>,1:bool}
     */
    private function parseArguments(array $arguments): array
    {
        $write = false;
        $positionals = [];

        foreach ($arguments as $argument) {
            if ($argument === '--write') {
                $write = true;
                continue;
            }

            if ($argument === '--dry-run') {
                continue;
            }

            if (str_starts_with($argument, '--')) {
                throw new OpusConsoleException('OPUS_ADD_LANGUAGE_UNKNOWN_OPTION: ' . $argument);
            }

            $positionals[] = $argument;
        }

        return [$positionals, $write];
    }

    /**
     * @param array<string, mixed> $routesConfig
     * @return array<string, string>
     */
    private function plannedFiles(string $siteId, string $locale, string $defaultLocale, string $siteRoot, array $routesConfig): array
    {
        $files = [];
        $files[$siteRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'i18n' . DIRECTORY_SEPARATOR . $locale . '.json'] = $this->json($this->starterI18n($locale));

        $routes = $routesConfig['routes'] ?? [];
        if (!is_array($routes)) {
            throw new OpusConsoleException('OPUS_ADD_LANGUAGE_ROUTES_CONTRACT_INVALID');
        }

        foreach ($routes as $route) {
            if (!is_array($route)) {
                throw new OpusConsoleException('OPUS_ADD_LANGUAGE_ROUTE_CONTRACT_INVALID');
            }

            $module = (string)($route['module'] ?? '');
            if ($module === '') {
                throw new OpusConsoleException('OPUS_ADD_LANGUAGE_ROUTE_MODULE_MISSING');
            }

            $contentPattern = (string)($route['content'] ?? '');
            if ($contentPattern === '' || !str_contains($contentPattern, '{{lang}}')) {
                throw new OpusConsoleException('OPUS_ADD_LANGUAGE_ROUTE_CONTENT_PATTERN_INVALID: ' . (string)($route['id'] ?? 'unknown'));
            }

            $targetRelative = str_replace('{{lang}}', $locale, $contentPattern);
            $targetAbsolute = $siteRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $targetRelative);

            $sourceRelative = str_replace('{{lang}}', $defaultLocale, $contentPattern);
            $sourceAbsolute = $siteRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sourceRelative);
            $sourceContent = [];
            if (is_file($sourceAbsolute)) {
                $sourceContent = $this->readJson($sourceAbsolute, 'OPUS_ADD_LANGUAGE_SOURCE_CONTENT_JSON_INVALID');
            }

            $files[$targetAbsolute] = $this->json($this->starterContent($siteId, $module, $locale, $sourceContent));
        }

        return $files;
    }

    /**
     * @return array<string, string>
     */
    private function starterI18n(string $locale): array
    {
        $catalog = [
            'fr' => [
                'menu_label' => 'Navigation principale',
                'menu.home' => 'Accueil',
                'menu.pages' => 'Pages',
                'menu.articles' => 'Articles',
                'menu.rubriques' => 'Rubriques',
                'menu.documentation' => 'Documentation',
                'header_cta' => 'Démarrer',
                'explore_rubrics' => 'Explorer les rubriques',
                'read_skeleton' => 'Voir le squelette',
                'contract_label' => 'Contrat OPUS',
                'contract_module' => 'Rubriques = modules déclarés',
                'contract_score' => 'Rendu via templates .score',
                'contract_routes' => 'Menu basé sur les routes',
                'contract_no_wild' => 'Aucune page sauvage',
                'starter_map' => 'Structure générée',
                'open_rubric' => 'Ouvrir la rubrique',
                'footer_contract' => 'Site OPUS généré',
                'back_to_top' => 'Retour haut',
                'language_selector_label' => 'Sélecteur de langue',
                'language_short_label' => 'Langue',
                'language.fr' => 'Français',
                'language.en' => 'English',
                'language.de' => 'Deutsch',
                'language.es' => 'Español',
                'language.it' => 'Italiano',
                'language.pl' => 'Polski',
                'language.uk' => 'Українська',
                'language.cs' => 'Čeština',
            ],
            'en' => [
                'menu_label' => 'Main navigation',
                'menu.home' => 'Home',
                'menu.pages' => 'Pages',
                'menu.articles' => 'Articles',
                'menu.rubriques' => 'Rubrics',
                'menu.documentation' => 'Documentation',
                'header_cta' => 'Start',
                'explore_rubrics' => 'Explore rubrics',
                'read_skeleton' => 'View skeleton',
                'contract_label' => 'OPUS contract',
                'contract_module' => 'Rubrics = declared modules',
                'contract_score' => 'Rendered through .score templates',
                'contract_routes' => 'Route-based menu',
                'contract_no_wild' => 'No wild pages',
                'starter_map' => 'Generated structure',
                'open_rubric' => 'Open rubric',
                'footer_contract' => 'Generated OPUS site',
                'back_to_top' => 'Back to top',
                'language_selector_label' => 'Language selector',
                'language_short_label' => 'Language',
                'language.fr' => 'Français',
                'language.en' => 'English',
                'language.de' => 'Deutsch',
                'language.es' => 'Español',
                'language.it' => 'Italiano',
                'language.pl' => 'Polski',
                'language.uk' => 'Українська',
                'language.cs' => 'Čeština',
            ],
            'es' => [
                'menu_label' => 'Navegación principal',
                'menu.home' => 'Inicio',
                'menu.pages' => 'Páginas',
                'menu.articles' => 'Artículos',
                'menu.rubriques' => 'Secciones',
                'menu.documentation' => 'Documentación',
                'header_cta' => 'Empezar',
                'explore_rubrics' => 'Explorar secciones',
                'read_skeleton' => 'Ver el esqueleto',
                'contract_label' => 'Contrato OPUS',
                'contract_module' => 'Secciones = módulos declarados',
                'contract_score' => 'Renderizado con plantillas .score',
                'contract_routes' => 'Menú basado en rutas',
                'contract_no_wild' => 'Sin páginas salvajes',
                'starter_map' => 'Estructura generada',
                'open_rubric' => 'Abrir sección',
                'footer_contract' => 'Sitio OPUS generado',
                'back_to_top' => 'Volver arriba',
                'language_selector_label' => 'Selector de idioma',
                'language_short_label' => 'Idioma',
                'language.fr' => 'Français',
                'language.en' => 'English',
                'language.de' => 'Deutsch',
                'language.es' => 'Español',
                'language.it' => 'Italiano',
                'language.pl' => 'Polski',
                'language.uk' => 'Українська',
                'language.cs' => 'Čeština',
            ],
            'de' => [
                'menu_label' => 'Hauptnavigation',
                'menu.home' => 'Start',
                'menu.pages' => 'Seiten',
                'menu.articles' => 'Artikel',
                'menu.rubriques' => 'Rubriken',
                'menu.documentation' => 'Dokumentation',
                'header_cta' => 'Starten',
                'explore_rubrics' => 'Rubriken erkunden',
                'read_skeleton' => 'Skelett ansehen',
                'contract_label' => 'OPUS-Vertrag',
                'contract_module' => 'Rubriken = deklarierte Module',
                'contract_score' => 'Rendering über .score-Templates',
                'contract_routes' => 'Routenbasiertes Menü',
                'contract_no_wild' => 'Keine wilden Seiten',
                'starter_map' => 'Generierte Struktur',
                'open_rubric' => 'Rubrik öffnen',
                'footer_contract' => 'Generierte OPUS-Site',
                'back_to_top' => 'Nach oben',
                'language_selector_label' => 'Sprachauswahl',
                'language_short_label' => 'Sprache',
                'language.fr' => 'Français',
                'language.en' => 'English',
                'language.de' => 'Deutsch',
                'language.es' => 'Español',
                'language.it' => 'Italiano',
                'language.pl' => 'Polski',
                'language.uk' => 'Українська',
                'language.cs' => 'Čeština',
            ],
            'it' => [
                'menu_label' => 'Navigazione principale',
                'menu.home' => 'Home',
                'menu.pages' => 'Pagine',
                'menu.articles' => 'Articoli',
                'menu.rubriques' => 'Rubriche',
                'menu.documentation' => 'Documentazione',
                'header_cta' => 'Inizia',
                'explore_rubrics' => 'Esplora rubriche',
                'read_skeleton' => 'Vedi scheletro',
                'contract_label' => 'Contratto OPUS',
                'contract_module' => 'Rubriche = moduli dichiarati',
                'contract_score' => 'Rendering tramite template .score',
                'contract_routes' => 'Menu basato sulle route',
                'contract_no_wild' => 'Nessuna pagina selvaggia',
                'starter_map' => 'Struttura generata',
                'open_rubric' => 'Apri rubrica',
                'footer_contract' => 'Sito OPUS generato',
                'back_to_top' => 'Torna su',
                'language_selector_label' => 'Selettore lingua',
                'language_short_label' => 'Lingua',
                'language.fr' => 'Français',
                'language.en' => 'English',
                'language.de' => 'Deutsch',
                'language.es' => 'Español',
                'language.it' => 'Italiano',
                'language.pl' => 'Polski',
                'language.uk' => 'Українська',
                'language.cs' => 'Čeština',
            ],
            'pl' => [
                'menu_label' => 'Nawigacja główna',
                'menu.home' => 'Start',
                'menu.pages' => 'Strony',
                'menu.articles' => 'Artykuły',
                'menu.rubriques' => 'Rubryki',
                'menu.documentation' => 'Dokumentacja',
                'header_cta' => 'Start',
                'explore_rubrics' => 'Przeglądaj rubryki',
                'read_skeleton' => 'Zobacz szkielet',
                'contract_label' => 'Kontrakt OPUS',
                'contract_module' => 'Rubryki = zadeklarowane moduły',
                'contract_score' => 'Renderowanie przez szablony .score',
                'contract_routes' => 'Menu oparte na trasach',
                'contract_no_wild' => 'Bez dzikich stron',
                'starter_map' => 'Wygenerowana struktura',
                'open_rubric' => 'Otwórz rubrykę',
                'footer_contract' => 'Wygenerowana strona OPUS',
                'back_to_top' => 'Do góry',
                'language_selector_label' => 'Wybór języka',
                'language_short_label' => 'Język',
                'language.fr' => 'Français',
                'language.en' => 'English',
                'language.de' => 'Deutsch',
                'language.es' => 'Español',
                'language.it' => 'Italiano',
                'language.pl' => 'Polski',
                'language.uk' => 'Українська',
                'language.cs' => 'Čeština',
            ],
            'cs' => [
                'menu_label' => 'Hlavní navigace',
                'menu.home' => 'Domů',
                'menu.pages' => 'Stránky',
                'menu.articles' => 'Články',
                'menu.rubriques' => 'Rubriky',
                'menu.documentation' => 'Dokumentace',
                'header_cta' => 'Začít',
                'explore_rubrics' => 'Prozkoumat rubriky',
                'read_skeleton' => 'Zobrazit skelet',
                'contract_label' => 'Kontrakt OPUS',
                'contract_module' => 'Rubriky = deklarované moduly',
                'contract_score' => 'Renderování přes .score šablony',
                'contract_routes' => 'Menu založené na routách',
                'contract_no_wild' => 'Žádné divoké stránky',
                'starter_map' => 'Vygenerovaná struktura',
                'open_rubric' => 'Otevřít rubriku',
                'footer_contract' => 'Vygenerovaný web OPUS',
                'back_to_top' => 'Zpět nahoru',
                'language_selector_label' => 'Výběr jazyka',
                'language_short_label' => 'Jazyk',
                'language.fr' => 'Français',
                'language.en' => 'English',
                'language.de' => 'Deutsch',
                'language.es' => 'Español',
                'language.it' => 'Italiano',
                'language.pl' => 'Polski',
                'language.uk' => 'Українська',
                'language.cs' => 'Čeština',
            ],
            'uk' => [
                'menu_label' => 'Основна навігація',
                'menu.home' => 'Головна',
                'menu.pages' => 'Сторінки',
                'menu.articles' => 'Статті',
                'menu.rubriques' => 'Рубрики',
                'menu.documentation' => 'Документація',
                'header_cta' => 'Почати',
                'explore_rubrics' => 'Переглянути рубрики',
                'read_skeleton' => 'Переглянути скелет',
                'contract_label' => 'Контракт OPUS',
                'contract_module' => 'Рубрики = оголошені модулі',
                'contract_score' => 'Рендер через шаблони .score',
                'contract_routes' => 'Меню на основі маршрутів',
                'contract_no_wild' => 'Без диких сторінок',
                'starter_map' => 'Згенерована структура',
                'open_rubric' => 'Відкрити рубрику',
                'footer_contract' => 'Згенерований сайт OPUS',
                'back_to_top' => 'Нагору',
                'language_selector_label' => 'Вибір мови',
                'language_short_label' => 'Мова',
                'language.fr' => 'Français',
                'language.en' => 'English',
                'language.de' => 'Deutsch',
                'language.es' => 'Español',
                'language.it' => 'Italiano',
                'language.pl' => 'Polski',
                'language.uk' => 'Українська',
                'language.cs' => 'Čeština',
            ],
        ];

        return $catalog[$locale] ?? $this->fallbackI18n($locale);
    }

    /**
     * @param array<string, mixed> $sourceContent
     * @return array<string, string>
     */
    private function starterContent(string $siteId, string $module, string $locale, array $sourceContent): array
    {
        $translated = $this->translatedContent($siteId, $module, $locale);
        if ($translated !== []) {
            return $translated;
        }

        $copy = [];
        foreach ($sourceContent as $key => $value) {
            if (is_scalar($value)) {
                $copy[(string)$key] = (string)$value;
            }
        }

        if ($copy === []) {
            $copy = [
                'kicker' => strtoupper($locale) . ' starter',
                'title' => $module,
                'subtitle' => 'Generated localized starter content. Replace it for the project needs.',
                'description' => 'Generated localized starter content.',
                'primary_title' => 'Responsibility',
                'primary_text' => 'Patch this module for the project needs.',
                'secondary_title' => 'Customize',
                'secondary_text' => 'Replace this content after generation.',
            ];
        }

        $copy['_translation_status'] = 'starter-copy-from-default-locale';
        $copy['_locale'] = $locale;
        return $copy;
    }

    /**
     * @return array<string, string>
     */
    private function translatedContent(string $siteId, string $module, string $locale): array
    {
        $catalog = [
            'en' => [
                'Home' => [
                    'kicker' => 'OPUS starter',
                    'title' => 'New site ' . $siteId . '',
                    'subtitle' => 'A professional skeleton to start an OPUS modular site: pages, articles, rubrics and documentation, without wild page creation.',
                    'section_title' => 'Rubrics ready to specialize.',
                    'section_intro' => 'Each block below is a route to a declared module. Replace the content, keep the contract.',
                ],
                'Pages' => [
                    'kicker' => 'Pages module',
                    'title' => 'Editorial pages',
                    'subtitle' => 'Entry point for static content, presentations, legal information or institutional pages.',
                    'description' => 'Structure for simple, clean and localized pages.',
                    'primary_title' => 'Responsibility',
                    'primary_text' => 'This module owns editorial pages without scattering page logic into public/index.php.',
                    'secondary_title' => 'Customize',
                    'secondary_text' => 'Add your content under resources/content, then adapt the module .score templates.',
                ],
                'Articles' => [
                    'kicker' => 'Articles module',
                    'title' => 'Articles and publications',
                    'subtitle' => 'Entry point for notes, news, product announcements or long-form publications.',
                    'description' => 'Structure for future publications and archives.',
                    'primary_title' => 'Responsibility',
                    'primary_text' => 'This module shows where published content, listing services and article templates belong.',
                    'secondary_title' => 'Customize',
                    'secondary_text' => 'Turn this rubric into a real editorial stream without imposing external dependencies.',
                ],
                'Rubriques' => [
                    'kicker' => 'Rubrics module',
                    'title' => 'Application rubrics',
                    'subtitle' => 'Entry point to organize the main business areas of the site.',
                    'description' => 'Structure for sections, categories and functional areas.',
                    'primary_title' => 'Responsibility',
                    'primary_text' => 'This module represents the ASAP principle: a visible rubric maps to a module or module route.',
                    'secondary_title' => 'Customize',
                    'secondary_text' => 'Add your own business modules with composer opus:create-module, then connect them through routes.',
                ],
                'Documentation' => [
                    'kicker' => 'Documentation module',
                    'title' => 'Site documentation',
                    'subtitle' => 'Entry point to explain the generated structure and guide implementation.',
                    'description' => 'Structure for developer help and project documentation.',
                    'primary_title' => 'Responsibility',
                    'primary_text' => 'This module helps the team understand where to place content, templates, routes and modules.',
                    'secondary_title' => 'Customize',
                    'secondary_text' => 'Replace this help with your product or project documentation.',
                ],
            ],
            'de' => [
                'Home' => [
                    'kicker' => 'OPUS-Starter',
                    'title' => 'Neue Website ' . $siteId . '',
                    'subtitle' => 'Ein professionelles Grundgerüst für eine modulare OPUS-Website: Seiten, Artikel, Rubriken und Dokumentation, ohne wilde Seitenerstellung.',
                    'section_title' => 'Rubriken bereit zur Spezialisierung.',
                    'section_intro' => 'Jeder Block unten ist eine Route zu einem deklarierten Modul. Ersetzen Sie den Inhalt und behalten Sie den Vertrag bei.',
                ],
                'Pages' => [
                    'kicker' => 'Modul Seiten',
                    'title' => 'Redaktionelle Seiten',
                    'subtitle' => 'Einstiegspunkt für statische Inhalte, Präsentationen, rechtliche Informationen oder institutionelle Seiten.',
                    'description' => 'Struktur für einfache, saubere und lokalisierte Seiten.',
                    'primary_title' => 'Verantwortung',
                    'primary_text' => 'Dieses Modul besitzt die redaktionellen Seiten, ohne Seitenlogik in public/index.php zu verteilen.',
                    'secondary_title' => 'Anpassen',
                    'secondary_text' => 'Fügen Sie Ihre Inhalte unter resources/content hinzu und passen Sie danach die .score-Templates des Moduls an.',
                ],
                'Articles' => [
                    'kicker' => 'Modul Artikel',
                    'title' => 'Artikel und Veröffentlichungen',
                    'subtitle' => 'Einstiegspunkt für Notizen, Nachrichten, Produktankündigungen oder längere Veröffentlichungen.',
                    'description' => 'Struktur für künftige Veröffentlichungen und Archive.',
                    'primary_title' => 'Verantwortung',
                    'primary_text' => 'Dieses Modul zeigt, wo veröffentlichte Inhalte, Listing-Services und Artikel-Templates hingehören.',
                    'secondary_title' => 'Anpassen',
                    'secondary_text' => 'Verwandeln Sie diese Rubrik in einen echten redaktionellen Bereich, ohne externe Abhängigkeiten aufzuzwingen.',
                ],
                'Rubriques' => [
                    'kicker' => 'Modul Rubriken',
                    'title' => 'Anwendungsrubriken',
                    'subtitle' => 'Einstiegspunkt zur Organisation der wichtigsten Geschäftsbereiche der Website.',
                    'description' => 'Struktur für Bereiche, Kategorien und funktionale Zonen.',
                    'primary_title' => 'Verantwortung',
                    'primary_text' => 'Dieses Modul repräsentiert das ASAP-Prinzip: Eine sichtbare Rubrik entspricht einem Modul oder einer Modulroute.',
                    'secondary_title' => 'Anpassen',
                    'secondary_text' => 'Fügen Sie eigene Fachmodule mit composer opus:create-module hinzu und verbinden Sie sie anschließend über Routen.',
                ],
                'Documentation' => [
                    'kicker' => 'Modul Dokumentation',
                    'title' => 'Website-Dokumentation',
                    'subtitle' => 'Einstiegspunkt, um die erzeugte Struktur zu erklären und die Implementierung zu leiten.',
                    'description' => 'Struktur für Entwicklerhilfe und Projektdokumentation.',
                    'primary_title' => 'Verantwortung',
                    'primary_text' => 'Dieses Modul hilft dem Team zu verstehen, wo Inhalte, Templates, Routen und Module platziert werden.',
                    'secondary_title' => 'Anpassen',
                    'secondary_text' => 'Ersetzen Sie diese Hilfe durch Ihre Produkt- oder Projektdokumentation.',
                ],
            ],
            'es' => [
                'Home' => [
                    'kicker' => 'Starter OPUS',
                    'title' => 'Nuevo sitio ' . $siteId . '',
                    'subtitle' => 'Un esqueleto profesional para iniciar un sitio modular OPUS: páginas, artículos, secciones y documentación, sin creación salvaje de páginas.',
                    'section_title' => 'Secciones listas para especializarse.',
                    'section_intro' => 'Cada bloque inferior es una ruta hacia un módulo declarado. Sustituya el contenido y conserve el contrato.',
                ],
                'Pages' => [
                    'kicker' => 'Módulo Páginas',
                    'title' => 'Páginas editoriales',
                    'subtitle' => 'Punto de entrada para contenidos estáticos, presentaciones, información legal o páginas institucionales.',
                    'description' => 'Estructura para páginas simples, limpias y localizadas.',
                    'primary_title' => 'Responsabilidad',
                    'primary_text' => 'Este módulo debe contener las páginas editoriales del sitio, sin dispersar lógica de página en public/index.php.',
                    'secondary_title' => 'Personalizar',
                    'secondary_text' => 'Añada sus contenidos en resources/content y adapte luego las plantillas .score del módulo.',
                ],
                'Articles' => [
                    'kicker' => 'Módulo Artículos',
                    'title' => 'Artículos y publicaciones',
                    'subtitle' => 'Punto de entrada para notas, novedades, anuncios de producto o publicaciones extensas.',
                    'description' => 'Estructura para futuras publicaciones y archivos.',
                    'primary_title' => 'Responsabilidad',
                    'primary_text' => 'Este módulo muestra dónde colocar contenidos publicados, servicios de listado y plantillas de artículo.',
                    'secondary_title' => 'Personalizar',
                    'secondary_text' => 'Convierta esta sección en un flujo editorial real sin imponer dependencias externas.',
                ],
                'Rubriques' => [
                    'kicker' => 'Módulo Secciones',
                    'title' => 'Secciones aplicativas',
                    'subtitle' => 'Punto de entrada para organizar las grandes zonas funcionales del sitio.',
                    'description' => 'Estructura para secciones, categorías y espacios funcionales.',
                    'primary_title' => 'Responsabilidad',
                    'primary_text' => 'Este módulo representa el principio ASAP: una sección visible corresponde a un módulo o a una ruta de módulo.',
                    'secondary_title' => 'Personalizar',
                    'secondary_text' => 'Añada sus propios módulos de negocio con composer opus:create-module y enlazarlos mediante rutas.',
                ],
                'Documentation' => [
                    'kicker' => 'Módulo Documentación',
                    'title' => 'Documentación del sitio',
                    'subtitle' => 'Punto de entrada para explicar la estructura generada y guiar la implementación.',
                    'description' => 'Estructura para ayuda de desarrollo y documentación de proyecto.',
                    'primary_title' => 'Responsabilidad',
                    'primary_text' => 'Este módulo ayuda al equipo a entender dónde colocar contenidos, plantillas, rutas y módulos.',
                    'secondary_title' => 'Personalizar',
                    'secondary_text' => 'Sustituya esta ayuda por su documentación de producto o proyecto.',
                ],
            ],
            'it' => [
                'Home' => [
                    'kicker' => 'Starter OPUS',
                    'title' => 'Nuovo sito ' . $siteId . '',
                    'subtitle' => 'Uno scheletro professionale per avviare un sito modulare OPUS: pagine, articoli, rubriche e documentazione, senza creazione selvaggia di pagine.',
                    'section_title' => 'Rubriche pronte da specializzare.',
                    'section_intro' => 'Ogni riquadro qui sotto è una route verso un modulo dichiarato. Sostituite il contenuto e mantenete il contratto.',
                ],
                'Pages' => [
                    'kicker' => 'Modulo Pagine',
                    'title' => 'Pagine editoriali',
                    'subtitle' => 'Punto di ingresso per contenuti statici, presentazioni, informazioni legali o pagine istituzionali.',
                    'description' => 'Struttura per pagine semplici, pulite e localizzate.',
                    'primary_title' => 'Responsabilità',
                    'primary_text' => 'Questo modulo deve contenere le pagine editoriali del sito, senza disperdere logica di pagina in public/index.php.',
                    'secondary_title' => 'Personalizzare',
                    'secondary_text' => 'Aggiungete i contenuti in resources/content, poi adattate i template .score del modulo.',
                ],
                'Articles' => [
                    'kicker' => 'Modulo Articoli',
                    'title' => 'Articoli e pubblicazioni',
                    'subtitle' => 'Punto di ingresso per note, notizie, annunci di prodotto o pubblicazioni lunghe.',
                    'description' => 'Struttura per future pubblicazioni e archivi.',
                    'primary_title' => 'Responsabilità',
                    'primary_text' => 'Questo modulo mostra dove collocare contenuti pubblicati, servizi di elenco e template articolo.',
                    'secondary_title' => 'Personalizzare',
                    'secondary_text' => 'Trasformate questa rubrica in un vero flusso editoriale senza imporre dipendenze esterne.',
                ],
                'Rubriques' => [
                    'kicker' => 'Modulo Rubriche',
                    'title' => 'Rubriche applicative',
                    'subtitle' => 'Punto di ingresso per organizzare le grandi aree funzionali del sito.',
                    'description' => 'Struttura per sezioni, categorie e aree funzionali.',
                    'primary_title' => 'Responsabilità',
                    'primary_text' => 'Questo modulo rappresenta il principio ASAP: una rubrica visibile corrisponde a un modulo o a una route di modulo.',
                    'secondary_title' => 'Personalizzare',
                    'secondary_text' => 'Aggiungete i vostri moduli métier con composer opus:create-module e collegateli tramite route.',
                ],
                'Documentation' => [
                    'kicker' => 'Modulo Documentazione',
                    'title' => 'Documentazione del sito',
                    'subtitle' => 'Punto di ingresso per spiegare la struttura generata e guidare l’implementazione.',
                    'description' => 'Struttura per aiuto sviluppatore e documentazione progetto.',
                    'primary_title' => 'Responsabilità',
                    'primary_text' => 'Questo modulo aiuta il team a capire dove collocare contenuti, template, route e moduli.',
                    'secondary_title' => 'Personalizzare',
                    'secondary_text' => 'Sostituite questo aiuto con la documentazione del prodotto o del progetto.',
                ],
            ],
            'pl' => [
                'Home' => [
                    'kicker' => 'Starter OPUS',
                    'title' => 'Nowa witryna ' . $siteId . '',
                    'subtitle' => 'Profesjonalny szkielet startowy dla modułowej witryny OPUS: strony, artykuły, rubryki i dokumentacja, bez dzikiego tworzenia stron.',
                    'section_title' => 'Rubryki gotowe do specjalizacji.',
                    'section_intro' => 'Każdy blok poniżej jest trasą do zadeklarowanego modułu. Zastąp treść i zachowaj kontrakt.',
                ],
                'Pages' => [
                    'kicker' => 'Moduł Strony',
                    'title' => 'Strony redakcyjne',
                    'subtitle' => 'Punkt wejścia dla treści statycznych, prezentacji, informacji prawnych lub stron instytucjonalnych.',
                    'description' => 'Struktura dla prostych, czystych i lokalizowanych stron.',
                    'primary_title' => 'Odpowiedzialność',
                    'primary_text' => 'Ten moduł przechowuje strony redakcyjne witryny, bez rozpraszania logiki strony w public/index.php.',
                    'secondary_title' => 'Dostosowanie',
                    'secondary_text' => 'Dodaj treści w resources/content, następnie dostosuj szablony .score modułu.',
                ],
                'Articles' => [
                    'kicker' => 'Moduł Artykuły',
                    'title' => 'Artykuły i publikacje',
                    'subtitle' => 'Punkt wejścia dla notatek, aktualności, ogłoszeń produktowych lub dłuższych publikacji.',
                    'description' => 'Struktura dla przyszłych publikacji i archiwów.',
                    'primary_title' => 'Odpowiedzialność',
                    'primary_text' => 'Ten moduł pokazuje, gdzie umieszczać publikowane treści, usługi listowania i szablony artykułów.',
                    'secondary_title' => 'Dostosowanie',
                    'secondary_text' => 'Przekształć tę rubrykę w prawdziwy strumień redakcyjny bez narzucania zewnętrznych zależności.',
                ],
                'Rubriques' => [
                    'kicker' => 'Moduł Rubryki',
                    'title' => 'Rubryki aplikacyjne',
                    'subtitle' => 'Punkt wejścia do organizowania głównych obszarów funkcjonalnych witryny.',
                    'description' => 'Struktura dla sekcji, kategorii i obszarów funkcjonalnych.',
                    'primary_title' => 'Odpowiedzialność',
                    'primary_text' => 'Ten moduł reprezentuje zasadę ASAP: widoczna rubryka odpowiada modułowi albo trasie modułu.',
                    'secondary_title' => 'Dostosowanie',
                    'secondary_text' => 'Dodaj własne moduły biznesowe poleceniem composer opus:create-module, a następnie połącz je trasami.',
                ],
                'Documentation' => [
                    'kicker' => 'Moduł Dokumentacja',
                    'title' => 'Dokumentacja witryny',
                    'subtitle' => 'Punkt wejścia do wyjaśniania wygenerowanej struktury i prowadzenia implementacji.',
                    'description' => 'Struktura dla pomocy deweloperskiej i dokumentacji projektu.',
                    'primary_title' => 'Odpowiedzialność',
                    'primary_text' => 'Ten moduł pomaga zespołowi zrozumieć, gdzie umieszczać treści, szablony, trasy i moduły.',
                    'secondary_title' => 'Dostosowanie',
                    'secondary_text' => 'Zastąp tę pomoc dokumentacją produktu lub projektu.',
                ],
            ],
            'cs' => [
                'Home' => [
                    'kicker' => 'OPUS starter',
                    'title' => 'Nový web ' . $siteId . '',
                    'subtitle' => 'Profesionální kostra pro spuštění modulárního webu OPUS: stránky, články, rubriky a dokumentace, bez divokého vytváření stránek.',
                    'section_title' => 'Rubriky připravené ke specializaci.',
                    'section_intro' => 'Každý blok níže je routa k deklarovanému modulu. Nahraďte obsah a zachovejte kontrakt.',
                ],
                'Pages' => [
                    'kicker' => 'Modul Stránky',
                    'title' => 'Redakční stránky',
                    'subtitle' => 'Vstupní bod pro statický obsah, prezentace, právní informace nebo institucionální stránky.',
                    'description' => 'Struktura pro jednoduché, čisté a lokalizované stránky.',
                    'primary_title' => 'Odpovědnost',
                    'primary_text' => 'Tento modul vlastní redakční stránky webu, bez rozptýlení logiky stránky do public/index.php.',
                    'secondary_title' => 'Přizpůsobit',
                    'secondary_text' => 'Přidejte obsah do resources/content a poté upravte .score šablony modulu.',
                ],
                'Articles' => [
                    'kicker' => 'Modul Články',
                    'title' => 'Články a publikace',
                    'subtitle' => 'Vstupní bod pro poznámky, novinky, produktová oznámení nebo dlouhé publikace.',
                    'description' => 'Struktura pro budoucí publikace a archivy.',
                    'primary_title' => 'Odpovědnost',
                    'primary_text' => 'Tento modul ukazuje, kam patří publikovaný obsah, služby výpisu a šablony článků.',
                    'secondary_title' => 'Přizpůsobit',
                    'secondary_text' => 'Proměňte tuto rubriku ve skutečný redakční proud bez vynucování externích závislostí.',
                ],
                'Rubriques' => [
                    'kicker' => 'Modul Rubriky',
                    'title' => 'Aplikační rubriky',
                    'subtitle' => 'Vstupní bod pro organizaci hlavních funkčních oblastí webu.',
                    'description' => 'Struktura pro sekce, kategorie a funkční oblasti.',
                    'primary_title' => 'Odpovědnost',
                    'primary_text' => 'Tento modul představuje princip ASAP: viditelná rubrika odpovídá modulu nebo routě modulu.',
                    'secondary_title' => 'Přizpůsobit',
                    'secondary_text' => 'Přidejte vlastní obchodní moduly pomocí composer opus:create-module a propojte je routami.',
                ],
                'Documentation' => [
                    'kicker' => 'Modul Dokumentace',
                    'title' => 'Dokumentace webu',
                    'subtitle' => 'Vstupní bod pro vysvětlení vygenerované struktury a vedení implementace.',
                    'description' => 'Struktura pro vývojářskou pomoc a projektovou dokumentaci.',
                    'primary_title' => 'Odpovědnost',
                    'primary_text' => 'Tento modul pomáhá týmu pochopit, kam umístit obsah, šablony, routy a moduly.',
                    'secondary_title' => 'Přizpůsobit',
                    'secondary_text' => 'Nahraďte tuto nápovědu dokumentací produktu nebo projektu.',
                ],
            ],
            'uk' => [
                'Home' => [
                    'kicker' => 'Старт OPUS',
                    'title' => 'Новий сайт ' . $siteId . '',
                    'subtitle' => 'Професійний каркас для старту модульного сайту OPUS: сторінки, статті, рубрики та документація, без дикого створення сторінок.',
                    'section_title' => 'Рубрики готові до спеціалізації.',
                    'section_intro' => 'Кожен блок нижче є маршрутом до оголошеного модуля. Замініть вміст і збережіть контракт.',
                ],
                'Pages' => [
                    'kicker' => 'Модуль Сторінки',
                    'title' => 'Редакційні сторінки',
                    'subtitle' => 'Точка входу для статичного вмісту, презентацій, юридичної інформації або інституційних сторінок.',
                    'description' => 'Структура для простих, чистих і локалізованих сторінок.',
                    'primary_title' => 'Відповідальність',
                    'primary_text' => 'Цей модуль відповідає за редакційні сторінки сайту без розсіювання логіки сторінок у public/index.php.',
                    'secondary_title' => 'Налаштувати',
                    'secondary_text' => 'Додайте вміст у resources/content, потім адаптуйте .score шаблони модуля.',
                ],
                'Articles' => [
                    'kicker' => 'Модуль Статті',
                    'title' => 'Статті та публікації',
                    'subtitle' => 'Точка входу для нотаток, новин, оголошень продукту або довгих публікацій.',
                    'description' => 'Структура для майбутніх публікацій та архівів.',
                    'primary_title' => 'Відповідальність',
                    'primary_text' => 'Цей модуль показує, де розміщувати опублікований вміст, сервіси списків і шаблони статей.',
                    'secondary_title' => 'Налаштувати',
                    'secondary_text' => 'Перетворіть цю рубрику на справжній редакційний потік без нав’язування зовнішніх залежностей.',
                ],
                'Rubriques' => [
                    'kicker' => 'Модуль Рубрики',
                    'title' => 'Прикладні рубрики',
                    'subtitle' => 'Точка входу для організації головних функціональних зон сайту.',
                    'description' => 'Структура для розділів, категорій і функціональних областей.',
                    'primary_title' => 'Відповідальність',
                    'primary_text' => 'Цей модуль представляє принцип ASAP: видима рубрика відповідає модулю або маршруту модуля.',
                    'secondary_title' => 'Налаштувати',
                    'secondary_text' => 'Додайте власні бізнес-модулі за допомогою composer opus:create-module, потім з’єднайте їх маршрутами.',
                ],
                'Documentation' => [
                    'kicker' => 'Модуль Документація',
                    'title' => 'Документація сайту',
                    'subtitle' => 'Точка входу для пояснення згенерованої структури та супроводу реалізації.',
                    'description' => 'Структура для допомоги розробнику та документації проєкту.',
                    'primary_title' => 'Відповідальність',
                    'primary_text' => 'Цей модуль допомагає команді зрозуміти, де розміщувати вміст, шаблони, маршрути та модулі.',
                    'secondary_title' => 'Налаштувати',
                    'secondary_text' => 'Замініть цю довідку документацією продукту або проєкту.',
                ],
            ],
        ];

        if (!isset($catalog[$locale])) {
            return [];
        }

        return $catalog[$locale][$module] ?? [];
    }

    /**
     * @return array<string, string>
     */
    private function fallbackI18n(string $locale): array
    {
        return [
            'menu_label' => 'Main navigation',
            'menu.home' => 'Home',
            'menu.pages' => 'Pages',
            'menu.articles' => 'Articles',
            'menu.rubriques' => 'Rubrics',
            'menu.documentation' => 'Documentation',
            'header_cta' => 'Start',
            'explore_rubrics' => 'Explore rubrics',
            'read_skeleton' => 'View skeleton',
            'contract_label' => 'OPUS contract',
            'contract_module' => 'Rubrics = declared modules',
            'contract_score' => 'Rendered through .score templates',
            'contract_routes' => 'Route-based menu',
            'contract_no_wild' => 'No wild pages',
            'starter_map' => 'Generated structure',
            'open_rubric' => 'Open rubric',
            'footer_contract' => 'Generated OPUS site',
            'back_to_top' => 'Back to top',
                'language_selector_label' => 'Language selector',
                'language_short_label' => 'Language',
                'language.fr' => 'Français',
                'language.en' => 'English',
                'language.de' => 'Deutsch',
                'language.es' => 'Español',
                'language.it' => 'Italiano',
                'language.pl' => 'Polski',
                'language.uk' => 'Українська',
                'language.cs' => 'Čeština',
            '_translation_status' => 'starter-fallback-for-' . $locale,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path, string $errorCode): array
    {
        if (!is_file($path)) {
            throw new OpusConsoleException($errorCode . ': ' . $this->relativeDisplayPath($path));
        }

        $decoded = json_decode((string)file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new OpusConsoleException($errorCode . ': ' . $this->relativeDisplayPath($path));
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeJson(string $path, array $data, string $errorCode): void
    {
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || file_put_contents($path, $encoded . "\n") === false) {
            throw new OpusConsoleException($errorCode . ': ' . $this->relativeDisplayPath($path));
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(array $data): string
    {
        return (string)json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    private function absolutePath(string $relativePath): string
    {
        return rtrim($this->opusRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }

    private function relativeDisplayPath(string $absolutePath): string
    {
        $root = rtrim($this->opusRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($absolutePath, $root)) {
            return str_replace(DIRECTORY_SEPARATOR, '/', substr($absolutePath, strlen($root)));
        }

        return str_replace(DIRECTORY_SEPARATOR, '/', $absolutePath);
    }
}
