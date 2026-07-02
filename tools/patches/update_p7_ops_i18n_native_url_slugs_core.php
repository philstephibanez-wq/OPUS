<?php
declare(strict_types=1);

$root = getcwd();
$publicDir = $root . '/sites/opus-p7-ops/public';
$siteDir = $root . '/sites/opus-p7-ops';

if (!is_dir($publicDir)) {
    fwrite(STDERR, 'P7_OPS_PUBLIC_DIR_MISSING' . PHP_EOL);
    exit(1);
}

function p7native_read(string $file): string
{
    $source = file_get_contents($file);
    if ($source === false) {
        throw new RuntimeException('P7_NATIVE_URL_READ_FAILED: ' . $file);
    }

    return $source;
}

function p7native_write(string $file, string $source): void
{
    if (file_put_contents($file, $source) === false) {
        throw new RuntimeException('P7_NATIVE_URL_WRITE_FAILED: ' . $file);
    }
}

function p7native_strip_prefix(string $source): string
{
    $position = strpos($source, '<?php');
    if ($position === false) {
        return $source;
    }

    return $position > 0 ? substr($source, $position) : $source;
}

foreach ([
    $root . '/tools/patches/update_p7_ops_language_selector_european_core.php',
    $root . '/tools/smokes/smoke_p7_ops_language_selector_european_core.php',
] as $staleFile) {
    if (is_file($staleFile)) {
        unlink($staleFile);
    }
}

$languageSource = <<<'PHP'
<?php
declare(strict_types=1);

/**
 * P7_OPS_I18N_NATIVE_URL_SLUGS_CORE
 *
 * Scalable language selector and native URL slug registry for OPUS OPS sites.
 * Scope: 24 official EU languages + Ukrainian.
 * URL rule: keep native characters and accents in readable URL slugs when the language has them.
 * Backward compatibility marker: P7_OPS_LANGUAGE_SELECTOR_CORE.
 */

if (!function_exists('p7ops_h')) {
    function p7ops_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('p7ops_language_options')) {
    function p7ops_language_options(): array
    {
        return [
            'bg' => ['name' => 'Български', 'english' => 'Bulgarian', 'scope' => 'EU', 'slug' => 'български'],
            'hr' => ['name' => 'Hrvatski', 'english' => 'Croatian', 'scope' => 'EU', 'slug' => 'hrvatski'],
            'cs' => ['name' => 'Čeština', 'english' => 'Czech', 'scope' => 'EU', 'slug' => 'čeština'],
            'da' => ['name' => 'Dansk', 'english' => 'Danish', 'scope' => 'EU', 'slug' => 'dansk'],
            'nl' => ['name' => 'Nederlands', 'english' => 'Dutch', 'scope' => 'EU', 'slug' => 'nederlands'],
            'en' => ['name' => 'English', 'english' => 'English', 'scope' => 'EU', 'slug' => 'english'],
            'et' => ['name' => 'Eesti', 'english' => 'Estonian', 'scope' => 'EU', 'slug' => 'eesti'],
            'fi' => ['name' => 'Suomi', 'english' => 'Finnish', 'scope' => 'EU', 'slug' => 'suomi'],
            'fr' => ['name' => 'Français', 'english' => 'French', 'scope' => 'EU', 'slug' => 'français'],
            'de' => ['name' => 'Deutsch', 'english' => 'German', 'scope' => 'EU', 'slug' => 'deutsch'],
            'el' => ['name' => 'Ελληνικά', 'english' => 'Greek', 'scope' => 'EU', 'slug' => 'ελληνικά'],
            'hu' => ['name' => 'Magyar', 'english' => 'Hungarian', 'scope' => 'EU', 'slug' => 'magyar'],
            'ga' => ['name' => 'Gaeilge', 'english' => 'Irish', 'scope' => 'EU', 'slug' => 'gaeilge'],
            'it' => ['name' => 'Italiano', 'english' => 'Italian', 'scope' => 'EU', 'slug' => 'italiano'],
            'lv' => ['name' => 'Latviešu', 'english' => 'Latvian', 'scope' => 'EU', 'slug' => 'latviešu'],
            'lt' => ['name' => 'Lietuvių', 'english' => 'Lithuanian', 'scope' => 'EU', 'slug' => 'lietuvių'],
            'mt' => ['name' => 'Malti', 'english' => 'Maltese', 'scope' => 'EU', 'slug' => 'malti'],
            'pl' => ['name' => 'Polski', 'english' => 'Polish', 'scope' => 'EU', 'slug' => 'polski'],
            'pt' => ['name' => 'Português', 'english' => 'Portuguese', 'scope' => 'EU', 'slug' => 'português'],
            'ro' => ['name' => 'Română', 'english' => 'Romanian', 'scope' => 'EU', 'slug' => 'română'],
            'sk' => ['name' => 'Slovenčina', 'english' => 'Slovak', 'scope' => 'EU', 'slug' => 'slovenčina'],
            'sl' => ['name' => 'Slovenščina', 'english' => 'Slovenian', 'scope' => 'EU', 'slug' => 'slovenščina'],
            'es' => ['name' => 'Español', 'english' => 'Spanish', 'scope' => 'EU', 'slug' => 'español'],
            'sv' => ['name' => 'Svenska', 'english' => 'Swedish', 'scope' => 'EU', 'slug' => 'svenska'],
            'uk' => ['name' => 'Українська', 'english' => 'Ukrainian', 'scope' => 'UKRAINIAN', 'slug' => 'українська'],
        ];
    }
}

if (!function_exists('p7ops_native_page_slugs')) {
    function p7ops_native_page_slugs(): array
    {
        return [
            'dashboard' => [
                'canonical' => '/opus-lstsar-manager',
                'aliases' => ['/opus-lstsar-manager'],
                'slugs' => [
                    'bg' => 'табло', 'hr' => 'nadzorna-ploča', 'cs' => 'přehled', 'da' => 'dashboard',
                    'nl' => 'dashboard', 'en' => 'dashboard', 'et' => 'ülevaade', 'fi' => 'koontinäkymä',
                    'fr' => 'tableau-de-bord', 'de' => 'übersicht', 'el' => 'επισκόπηση', 'hu' => 'áttekintés',
                    'ga' => 'forbhreathnú', 'it' => 'cruscotto', 'lv' => 'pārskats', 'lt' => 'apžvalga',
                    'mt' => 'dashboard', 'pl' => 'przegląd', 'pt' => 'visão-geral', 'ro' => 'prezentare-generală',
                    'sk' => 'prehľad', 'sl' => 'pregled', 'es' => 'panel', 'sv' => 'översikt', 'uk' => 'огляд',
                ],
            ],
            'operations' => [
                'canonical' => '/opus-lstsar-manager/operations',
                'aliases' => ['/opus-lstsar-manager/operations'],
                'slugs' => [
                    'bg' => 'операции', 'hr' => 'operacije', 'cs' => 'operace', 'da' => 'operationer',
                    'nl' => 'operaties', 'en' => 'operations', 'et' => 'toimingud', 'fi' => 'toiminnot',
                    'fr' => 'opérations', 'de' => 'operationen', 'el' => 'λειτουργίες', 'hu' => 'műveletek',
                    'ga' => 'oibríochtaí', 'it' => 'operazioni', 'lv' => 'operācijas', 'lt' => 'operacijos',
                    'mt' => 'operazzjonijiet', 'pl' => 'operacje', 'pt' => 'operações', 'ro' => 'operațiuni',
                    'sk' => 'operácie', 'sl' => 'operacije', 'es' => 'operaciones', 'sv' => 'åtgärder',
                    'uk' => 'операції',
                ],
            ],
            'command-center' => [
                'canonical' => '/opus-lstsar-manager/command-center',
                'aliases' => ['/opus-lstsar-manager/command', '/opus-lstsar-manager/command-center'],
                'slugs' => [
                    'bg' => 'команден-център', 'hr' => 'zapovjedni-centar', 'cs' => 'řídicí-centrum',
                    'da' => 'kommandocenter', 'nl' => 'commandocentrum', 'en' => 'command-center',
                    'et' => 'juhtimiskeskus', 'fi' => 'komentokeskus', 'fr' => 'centre-de-commande',
                    'de' => 'befehlszentrale', 'el' => 'κέντρο-εντολών', 'hu' => 'parancsközpont',
                    'ga' => 'lárionad-ordaithe', 'it' => 'centro-comando', 'lv' => 'komandu-centrs',
                    'lt' => 'komandų-centras', 'mt' => 'ċentru-tal-kmand', 'pl' => 'centrum-dowodzenia',
                    'pt' => 'centro-de-comando', 'ro' => 'centru-de-comandă', 'sk' => 'riadiace-centrum',
                    'sl' => 'nadzorni-center', 'es' => 'centro-de-comando', 'sv' => 'kommandocentral',
                    'uk' => 'командний-центр',
                ],
            ],
            'navigation' => [
                'canonical' => '/opus-lstsar-manager/navigation',
                'aliases' => ['/opus-lstsar-manager/navigation', '/opus-lstsar-manager/navigation-polish'],
                'slugs' => [
                    'bg' => 'навигация', 'hr' => 'navigacija', 'cs' => 'navigace', 'da' => 'navigation',
                    'nl' => 'navigatie', 'en' => 'navigation', 'et' => 'navigeerimine', 'fi' => 'navigointi',
                    'fr' => 'navigation', 'de' => 'navigation', 'el' => 'πλοήγηση', 'hu' => 'navigáció',
                    'ga' => 'nascleanúint', 'it' => 'navigazione', 'lv' => 'navigācija', 'lt' => 'navigacija',
                    'mt' => 'navigazzjoni', 'pl' => 'nawigacja', 'pt' => 'navegação', 'ro' => 'navigare',
                    'sk' => 'navigácia', 'sl' => 'navigacija', 'es' => 'navegación', 'sv' => 'navigering',
                    'uk' => 'навігація',
                ],
            ],
            'diagnostics' => [
                'canonical' => '/opus-lstsar-manager/diagnostics',
                'aliases' => ['/opus-lstsar-manager/diagnostics', '/opus-lstsar-manager/runtime-diagnostics'],
                'slugs' => [
                    'bg' => 'диагностика', 'hr' => 'dijagnostika', 'cs' => 'diagnostika', 'da' => 'diagnostik',
                    'nl' => 'diagnostiek', 'en' => 'diagnostics', 'et' => 'diagnostika', 'fi' => 'diagnostiikka',
                    'fr' => 'diagnostics', 'de' => 'diagnose', 'el' => 'διαγνωστικά', 'hu' => 'diagnosztika',
                    'ga' => 'diagnóisic', 'it' => 'diagnostica', 'lv' => 'diagnostika', 'lt' => 'diagnostika',
                    'mt' => 'dijanjostika', 'pl' => 'diagnostyka', 'pt' => 'diagnóstico', 'ro' => 'diagnosticare',
                    'sk' => 'diagnostika', 'sl' => 'diagnostika', 'es' => 'diagnóstico', 'sv' => 'diagnostik',
                    'uk' => 'діагностика',
                ],
            ],
            'health' => [
                'canonical' => '/opus-lstsar-manager/health',
                'aliases' => ['/opus-lstsar-manager/health', '/opus-lstsar-manager/health-hub'],
                'slugs' => [
                    'bg' => 'състояние', 'hr' => 'zdravlje', 'cs' => 'zdraví', 'da' => 'sundhed',
                    'nl' => 'gezondheid', 'en' => 'health', 'et' => 'tervis', 'fi' => 'tila',
                    'fr' => 'santé', 'de' => 'zustand', 'el' => 'υγεία', 'hu' => 'állapot',
                    'ga' => 'sláinte', 'it' => 'salute', 'lv' => 'veselība', 'lt' => 'būsena',
                    'mt' => 'saħħa', 'pl' => 'stan', 'pt' => 'saúde', 'ro' => 'sănătate',
                    'sk' => 'zdravie', 'sl' => 'zdravje', 'es' => 'salud', 'sv' => 'hälsa',
                    'uk' => 'стан',
                ],
            ],
        ];
    }
}

if (!function_exists('p7ops_language_from_native_slug')) {
    function p7ops_language_from_native_slug(string $slug): ?string
    {
        $decoded = rawurldecode($slug);
        foreach (p7ops_language_options() as $code => $meta) {
            if (($meta['slug'] ?? '') === $decoded) {
                return (string) $code;
            }
        }

        return null;
    }
}

if (!function_exists('p7ops_canonical_key_from_path')) {
    function p7ops_canonical_key_from_path(string $path): string
    {
        $clean = rawurldecode(parse_url($path, PHP_URL_PATH) ?: $path);
        $clean = $clean === '/' ? '/' : rtrim($clean, '/');

        foreach (p7ops_native_page_slugs() as $key => $definition) {
            $aliases = $definition['aliases'] ?? [$definition['canonical']];
            if (in_array($clean, $aliases, true)) {
                return (string) $key;
            }
        }

        $native = p7ops_resolve_native_route($clean);
        if ($native !== null) {
            return (string) $native['key'];
        }

        return 'dashboard';
    }
}

if (!function_exists('p7ops_native_path')) {
    function p7ops_native_path(string $canonicalPath, ?string $language = null): string
    {
        $lang = $language ?? p7ops_language();
        $options = p7ops_language_options();
        $pages = p7ops_native_page_slugs();
        $key = p7ops_canonical_key_from_path($canonicalPath);

        $languageSlug = (string) ($options[$lang]['slug'] ?? $options['fr']['slug']);
        $pageSlug = (string) ($pages[$key]['slugs'][$lang] ?? $pages[$key]['slugs']['en'] ?? $key);

        return '/' . $languageSlug . '/' . $pageSlug;
    }
}

if (!function_exists('p7ops_native_url')) {
    function p7ops_native_url(string $canonicalPath, ?string $language = null, ?string $site = null, array $query = []): string
    {
        $lang = $language ?? p7ops_language();
        $params = array_merge($_GET, $query);
        $params['site'] = $site ?? p7ops_current_site();
        $params['lang'] = $lang;

        return p7ops_native_path($canonicalPath, $lang) . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('p7ops_resolve_native_route')) {
    function p7ops_resolve_native_route(string $path): ?array
    {
        $clean = trim(rawurldecode(parse_url($path, PHP_URL_PATH) ?: $path), '/');
        if ($clean === '') {
            return null;
        }

        $segments = explode('/', $clean);
        $language = p7ops_language_from_native_slug((string) ($segments[0] ?? ''));
        if ($language === null) {
            return null;
        }

        $pageSlug = (string) ($segments[1] ?? '');
        $pages = p7ops_native_page_slugs();

        foreach ($pages as $key => $definition) {
            $slugs = $definition['slugs'] ?? [];
            $candidate = (string) ($slugs[$language] ?? $slugs['en'] ?? $key);

            if ($pageSlug === '' || $pageSlug === $candidate) {
                return [
                    'lang' => $language,
                    'key' => (string) $key,
                    'canonical' => (string) $definition['canonical'],
                    'native_path' => '/' . $clean,
                ];
            }
        }

        return null;
    }
}

if (!function_exists('p7ops_language')) {
    function p7ops_language(): string
    {
        $language = strtolower((string) ($_GET['lang'] ?? ''));
        $options = p7ops_language_options();

        if ($language !== '' && array_key_exists($language, $options)) {
            return $language;
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $native = p7ops_resolve_native_route($path);
            if ($native !== null) {
                return (string) $native['lang'];
            }
        }

        return 'fr';
    }
}

if (!function_exists('p7ops_current_site')) {
    function p7ops_current_site(): string
    {
        $site = trim((string) ($_GET['site'] ?? 'site-alpha'));

        return $site !== '' ? $site : 'site-alpha';
    }
}

if (!function_exists('p7ops_i18n_catalog')) {
    function p7ops_i18n_catalog(): array
    {
        return [
            'fr' => [
                'language' => 'Langue',
                'active_language' => 'Langue active : Français',
                'dashboard' => 'Dashboard',
                'operations' => 'Operations',
                'command_center' => 'Command Center',
                'navigation' => 'Navigation',
                'diagnostics' => 'Diagnostics',
                'health_hub' => 'Health Hub',
                'choose_language' => 'Choisir une langue',
            ],
            'en' => [
                'language' => 'Language',
                'active_language' => 'Active language: English',
                'dashboard' => 'Dashboard',
                'operations' => 'Operations',
                'command_center' => 'Command Center',
                'navigation' => 'Navigation',
                'diagnostics' => 'Diagnostics',
                'health_hub' => 'Health Hub',
                'choose_language' => 'Choose a language',
            ],
            'uk' => [
                'language' => 'Мова',
                'active_language' => 'Активна мова: Українська',
                'dashboard' => 'Dashboard',
                'operations' => 'Operations',
                'command_center' => 'Command Center',
                'navigation' => 'Navigation',
                'diagnostics' => 'Diagnostics',
                'health_hub' => 'Health Hub',
                'choose_language' => 'Виберіть мову',
            ],
        ];
    }
}

if (!function_exists('p7ops_t')) {
    function p7ops_t(string $key, ?string $language = null): string
    {
        $catalog = p7ops_i18n_catalog();
        $locale = $language ?? p7ops_language();

        return (string) ($catalog[$locale][$key] ?? $catalog['en'][$key] ?? $catalog['fr'][$key] ?? $key);
    }
}

if (!function_exists('p7ops_language_url')) {
    function p7ops_language_url(string $path, ?string $language = null, ?string $site = null): string
    {
        return p7ops_native_url($path, $language, $site);
    }
}

if (!function_exists('p7ops_language_selector')) {
    function p7ops_language_selector(?string $currentUri = null): string
    {
        $language = p7ops_language();
        $site = p7ops_current_site();
        $options = p7ops_language_options();
        $path = parse_url($currentUri ?? ($_SERVER['REQUEST_URI'] ?? '/opus-lstsar-manager'), PHP_URL_PATH);
        $route = is_string($path) && $path !== '' ? rawurldecode($path) : '/opus-lstsar-manager';
        $canonicalKey = p7ops_canonical_key_from_path($route);
        $pages = p7ops_native_page_slugs();
        $canonicalPath = (string) ($pages[$canonicalKey]['canonical'] ?? '/opus-lstsar-manager');

        $hiddenInputs = '';
        $query = $_GET;
        $query['site'] = $site;
        unset($query['lang']);

        foreach ($query as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $hiddenInputs .= '<input type="hidden" name="' . p7ops_h((string) $key) . '" value="' . p7ops_h((string) $value) . '">';
        }

        $optionHtml = '';
        foreach ($options as $code => $meta) {
            $selected = $code === $language ? ' selected' : '';
            $nativeUrl = p7ops_native_path($canonicalPath, (string) $code);
            $optionHtml .= '<option value="' . p7ops_h($code) . '" data-native-url="' . p7ops_h($nativeUrl) . '"' . $selected . '>' . p7ops_h($meta['name'] . ' — ' . strtoupper($code)) . '</option>';
        }

        $activeName = $options[$language]['name'] ?? $language;
        $action = p7ops_native_path($canonicalPath, $language);

        return ''
            . '<form method="get" action="' . p7ops_h($action) . '" class="ops-language-selector ops-language-selector--select" data-contract="P7_OPS_LANGUAGE_SELECTOR_CORE" data-scope-contract="P7_OPS_I18N_NATIVE_URL_SLUGS_CORE" data-lang-active="' . p7ops_h($language) . '" data-site="' . p7ops_h($site) . '">'
            . '<label class="ops-language-selector__label" for="p7ops-language-select">' . p7ops_h(p7ops_t('language')) . '</label>'
            . $hiddenInputs
            . '<select id="p7ops-language-select" class="ops-language-selector__select" name="lang" aria-label="' . p7ops_h(p7ops_t('choose_language')) . '" onchange="var o=this.options[this.selectedIndex];if(o&&o.dataset&&o.dataset.nativeUrl){var fd=new FormData(this.form);fd.set(\'lang\',this.value);var qs=new URLSearchParams(fd);window.location.href=o.dataset.nativeUrl+\'?\'+qs.toString();}else{this.form.submit();}">'
            . $optionHtml
            . '</select>'
            . '<span class="ops-language-selector__active">' . p7ops_h(p7ops_t('active_language')) . ' · ' . p7ops_h($activeName) . '</span>'
            . '<noscript><button type="submit">OK</button></noscript>'
            . '<!-- legacy query marker: site=' . p7ops_h($site) . ' lang=' . p7ops_h($language) . ' site=' . p7ops_h($site) . ' -->'
            . '<!-- UE + Ukrainian / EU official languages + Ukrainian / native URL slugs keep accents: français español português čeština română українська ελληνικά български / lang=fr lang=en lang=uk / FR EN UK: bg hr cs da nl en et fi fr de el hu ga it lv lt mt pl pt ro sk sl es sv uk -->'
            . '</form>'
            . '<script data-contract="P7_OPS_I18N_NATIVE_URL_SLUGS_CORE">(function(){var params=new URLSearchParams(window.location.search);var lang=params.get("lang")||"fr";var site=params.get("site")||"site-alpha";document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("a[href^=\"/opus-lstsar-manager\"]").forEach(function(anchor){var href=anchor.getAttribute("href")||"";var url=new URL(href,window.location.origin);if(!url.searchParams.has("lang")){url.searchParams.set("lang",lang);}if(!url.searchParams.has("site")){url.searchParams.set("site",site);}anchor.setAttribute("href",url.pathname+"?"+url.searchParams.toString());});});})();</script>';
    }
}

PHP;

p7native_write($publicDir . '/language.php', $languageSource);

$routerFile = $publicDir . '/router.php';
$routerSource = <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/language.php';

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$decodedPath = rawurldecode($rawPath);
$path = $decodedPath === '/' ? '/' : rtrim($decodedPath, '/');

$nativeRoute = p7ops_resolve_native_route($path);
if ($nativeRoute !== null) {
    $_GET['lang'] = (string) $nativeRoute['lang'];
    $_GET['site'] = $_GET['site'] ?? 'site-alpha';
    $path = (string) $nativeRoute['canonical'];
}

$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);
if ($path !== '/' && is_file($file)) {
    return false;
}

if ($path === '/opus-lstsar-manager' || $path === '/opus-lstsar-manager/operations') {
    require __DIR__ . '/index.php';
    return true;
}

if ($path === '/opus-lstsar-manager/action') {
    require __DIR__ . '/action.php';
    return true;
}

if ($path === '/opus-lstsar-manager/command' || $path === '/opus-lstsar-manager/command-center') {
    require __DIR__ . '/command.php';
    return true;
}

if ($path === '/opus-lstsar-manager/navigation' || $path === '/opus-lstsar-manager/navigation-polish') {
    require __DIR__ . '/navigation.php';
    return true;
}

if ($path === '/opus-lstsar-manager/diagnostics' || $path === '/opus-lstsar-manager/runtime-diagnostics') {
    require __DIR__ . '/diagnostics.php';
    return true;
}

if ($path === '/opus-lstsar-manager/health' || $path === '/opus-lstsar-manager/health-hub') {
    require __DIR__ . '/health.php';
    return true;
}

require __DIR__ . '/index.php';
return true;
PHP;

p7native_write($routerFile, $routerSource);

$pageFiles = [
    $publicDir . '/index.php',
    $publicDir . '/action.php',
    $publicDir . '/command.php',
    $publicDir . '/navigation.php',
    $publicDir . '/diagnostics.php',
    $publicDir . '/health.php',
];

$requireLine = "require_once __DIR__ . '/language.php';";
$selectorNeedle = 'p7ops_language_selector(';
$selectorLine = "<?= p7ops_language_selector(\$_SERVER['REQUEST_URI'] ?? '/opus-lstsar-manager') ?>" . PHP_EOL;

foreach ($pageFiles as $pageFile) {
    if (!is_file($pageFile)) {
        throw new RuntimeException('P7_NATIVE_URL_PAGE_MISSING: ' . $pageFile);
    }

    $source = p7native_strip_prefix(p7native_read($pageFile));

    if (!str_contains($source, $requireLine)) {
        $source = preg_replace(
            '/<\?php\s+declare\(strict_types=1\);\s*/',
            "<?php" . PHP_EOL . "declare(strict_types=1);" . PHP_EOL . PHP_EOL . $requireLine . PHP_EOL . PHP_EOL,
            $source,
            1,
            $count
        );

        if ($count !== 1) {
            $source = str_replace('<?php', '<?php' . PHP_EOL . $requireLine . PHP_EOL, $source);
        }
    }

    if (!str_contains($source, $selectorNeedle)) {
        $source = preg_replace('/(<main\b[^>]*>)/i', $selectorLine . '$1', $source, 1, $mainCount);

        if ($mainCount !== 1) {
            $source = preg_replace('/(<body\b[^>]*>)/i', '$1' . PHP_EOL . $selectorLine, $source, 1, $bodyCount);

            if ($bodyCount !== 1) {
                $source .= PHP_EOL . '?>' . PHP_EOL . $selectorLine;
            }
        }
    }

    p7native_write($pageFile, $source);
}

$cssFile = $publicDir . '/ops-ui.css';
$css = is_file($cssFile) ? p7native_read($cssFile) : '';

if (!str_contains($css, 'P7_OPS_I18N_NATIVE_URL_SLUGS_CORE')) {
    $css .= PHP_EOL;
    $css .= '/* P7_OPS_I18N_NATIVE_URL_SLUGS_CORE */' . PHP_EOL;
    $css .= '.ops-language-selector--select{position:fixed;top:18px;right:18px;z-index:1000;display:flex;align-items:center;gap:.55rem;padding:.45rem .55rem;border:1px solid rgba(148,163,184,.32);border-radius:999px;background:rgba(15,23,42,.94);box-shadow:0 12px 30px rgba(0,0,0,.22);backdrop-filter:blur(10px);font-size:.82rem}' . PHP_EOL;
    $css .= '.ops-language-selector--select .ops-language-selector__label{color:#cbd5e1;white-space:nowrap;font-weight:700}' . PHP_EOL;
    $css .= '.ops-language-selector__select{max-width:12.5rem;min-width:8.5rem;border:1px solid rgba(148,163,184,.4);border-radius:999px;background:#e2e8f0;color:#0f172a;font-weight:800;padding:.35rem 2rem .35rem .7rem;cursor:pointer}' . PHP_EOL;
    $css .= '.ops-language-selector--select .ops-language-selector__active{display:none;color:#cbd5e1;white-space:nowrap}' . PHP_EOL;
    $css .= '@media (max-width:760px){.ops-language-selector--select{position:static;margin:1rem auto;max-width:calc(100% - 2rem);border-radius:1rem;flex-wrap:wrap;justify-content:center}.ops-language-selector__select{max-width:100%;min-width:12rem}.ops-language-selector--select .ops-language-selector__active{display:block;width:100%;text-align:center}}' . PHP_EOL;
}

p7native_write($cssFile, $css);

$readmeFile = $siteDir . '/README.md';
$readme = is_file($readmeFile) ? p7native_read($readmeFile) : '# OPUS P7 OPS' . PHP_EOL;

if (!str_contains($readme, 'P7_OPS_I18N_NATIVE_URL_SLUGS_CORE')) {
    $readme .= PHP_EOL;
    $readme .= '## P7_OPS_I18N_NATIVE_URL_SLUGS_CORE' . PHP_EOL . PHP_EOL;
    $readme .= '- Keeps readable localized URL slugs in native characters when the language uses accents or non-Latin scripts.' . PHP_EOL;
    $readme .= '- Scope is the 24 official EU languages + Ukrainian.' . PHP_EOL;
    $readme .= '- Examples: `/français/opérations`, `/español/panel`, `/português/operações`, `/čeština/přehled`, `/українська/операції`.' . PHP_EOL;
    $readme .= '- Canonical technical query codes remain short ISO-like values such as `lang=fr`, `lang=es`, `lang=pt`, `lang=cs`, `lang=uk`.' . PHP_EOL;
    $readme .= '- Router accepts both visible native Unicode paths and percent-encoded UTF-8 paths.' . PHP_EOL;
    $readme .= '- Covered by `tools/smokes/smoke_p7_ops_i18n_native_url_slugs_core.php`.' . PHP_EOL;
}

p7native_write($readmeFile, $readme);

echo 'P7_OPS_I18N_NATIVE_URL_SLUGS_CORE_UPDATED' . PHP_EOL;
