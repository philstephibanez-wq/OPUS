<?php
declare(strict_types=1);

$root = getcwd();
$publicDir = $root . '/sites/opus-p7-ops/public';
$siteDir = $root . '/sites/opus-p7-ops';

if (!is_dir($publicDir)) {
    fwrite(STDERR, 'P7_OPS_PUBLIC_DIR_MISSING' . PHP_EOL);
    exit(1);
}
if (!is_dir($siteDir)) {
    fwrite(STDERR, 'P7_OPS_SITE_DIR_MISSING' . PHP_EOL);
    exit(1);
}

function p7ls_read(string $file): string
{
    $source = file_get_contents($file);
    if ($source === false) {
        throw new RuntimeException('P7_LANGUAGE_SELECTOR_READ_FAILED: ' . $file);
    }

    return $source;
}

function p7ls_write(string $file, string $source): void
{
    if (file_put_contents($file, $source) === false) {
        throw new RuntimeException('P7_LANGUAGE_SELECTOR_WRITE_FAILED: ' . $file);
    }
}

function p7ls_strip_prefix(string $source): string
{
    $position = strpos($source, '<?php');
    if ($position === false) {
        return $source;
    }

    return $position > 0 ? substr($source, $position) : $source;
}

$languageFile = $publicDir . '/language.php';
$languageSource = <<<'PHP'
<?php
declare(strict_types=1);

/**
 * P7_OPS_LANGUAGE_SELECTOR_CORE
 *
 * Provides the visible OPS language selector and minimal i18n helpers.
 * Supported locales are deliberately explicit: fr and en.
 */

if (!function_exists('p7ops_h')) {
    function p7ops_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('p7ops_language')) {
    function p7ops_language(): string
    {
        $language = strtolower((string) ($_GET['lang'] ?? 'fr'));

        return in_array($language, ['fr', 'en'], true) ? $language : 'fr';
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
            ],
        ];
    }
}

if (!function_exists('p7ops_t')) {
    function p7ops_t(string $key, ?string $language = null): string
    {
        $catalog = p7ops_i18n_catalog();
        $locale = $language ?? p7ops_language();

        return (string) ($catalog[$locale][$key] ?? $catalog['fr'][$key] ?? $key);
    }
}

if (!function_exists('p7ops_language_url')) {
    function p7ops_language_url(string $path, ?string $language = null, ?string $site = null): string
    {
        $route = str_starts_with($path, '/') ? $path : '/' . $path;
        $query = [
            'site' => $site ?? p7ops_current_site(),
            'lang' => $language ?? p7ops_language(),
        ];

        return $route . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('p7ops_language_selector')) {
    function p7ops_language_selector(?string $currentUri = null): string
    {
        $language = p7ops_language();
        $site = p7ops_current_site();
        $path = parse_url($currentUri ?? ($_SERVER['REQUEST_URI'] ?? '/opus-lstsar-manager'), PHP_URL_PATH);
        $route = is_string($path) && $path !== '' ? $path : '/opus-lstsar-manager';

        $frUrl = p7ops_language_url($route, 'fr', $site);
        $enUrl = p7ops_language_url($route, 'en', $site);
        $frActive = $language === 'fr' ? ' is-active' : '';
        $enActive = $language === 'en' ? ' is-active' : '';

        return ''
            . '<aside class="ops-language-selector" data-contract="P7_OPS_LANGUAGE_SELECTOR_CORE" data-lang-active="' . p7ops_h($language) . '" data-site="' . p7ops_h($site) . '">'
            . '<span class="ops-language-selector__label">' . p7ops_h(p7ops_t('language')) . '</span>'
            . '<a class="ops-language-selector__choice' . $frActive . '" href="' . p7ops_h($frUrl) . '" hreflang="fr" lang="fr">FR</a>'
            . '<a class="ops-language-selector__choice' . $enActive . '" href="' . p7ops_h($enUrl) . '" hreflang="en" lang="en">EN</a>'
            . '<span class="ops-language-selector__active">' . p7ops_h(p7ops_t('active_language')) . '</span>'
            . '</aside>'
            . '<script data-contract="P7_OPS_LANGUAGE_SELECTOR_CORE">(function(){var params=new URLSearchParams(window.location.search);var lang=params.get("lang")||"fr";var site=params.get("site")||"site-alpha";document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("a[href^=\"/opus-lstsar-manager\"]").forEach(function(anchor){var href=anchor.getAttribute("href")||"";var url=new URL(href,window.location.origin);if(!url.searchParams.has("lang")){url.searchParams.set("lang",lang);}if(!url.searchParams.has("site")){url.searchParams.set("site",site);}anchor.setAttribute("href",url.pathname+"?"+url.searchParams.toString());});});})();</script>';
    }
}
PHP;

p7ls_write($languageFile, $languageSource);

$pageFiles = [
    $publicDir . '/index.php',
    $publicDir . '/action.php',
    $publicDir . '/command.php',
    $publicDir . '/navigation.php',
    $publicDir . '/diagnostics.php',
    $publicDir . '/health.php',
];

$requireLine = "require_once __DIR__ . '/language.php';";
$selectorLine = "<?= p7ops_language_selector(\$_SERVER['REQUEST_URI'] ?? '/opus-lstsar-manager') ?>" . PHP_EOL;

foreach ($pageFiles as $pageFile) {
    if (!is_file($pageFile)) {
        throw new RuntimeException('P7_LANGUAGE_SELECTOR_PAGE_MISSING: ' . $pageFile);
    }

    $source = p7ls_strip_prefix(p7ls_read($pageFile));

    if (!str_contains($source, $requireLine)) {
        $inserted = false;
        $source = preg_replace(
            '/<\?php\s+declare\(strict_types=1\);\s*/',
            "<?php" . PHP_EOL . "declare(strict_types=1);" . PHP_EOL . PHP_EOL . $requireLine . PHP_EOL . PHP_EOL,
            $source,
            1,
            $count
        );
        $inserted = $count === 1;

        if (!$inserted) {
            $source = str_replace('<?php', '<?php' . PHP_EOL . $requireLine . PHP_EOL, $source);
        }
    }

    if (!str_contains($source, 'p7ops_language_selector(')) {
        $source = preg_replace('/(<main\b[^>]*>)/i', $selectorLine . '$1', $source, 1, $mainCount);

        if ($mainCount !== 1) {
            $source = preg_replace('/(<body\b[^>]*>)/i', '$1' . PHP_EOL . $selectorLine, $source, 1, $bodyCount);

            if ($bodyCount !== 1) {
                $source .= PHP_EOL . '?>' . PHP_EOL . $selectorLine;
            }
        }
    }

    p7ls_write($pageFile, $source);
}

$cssFile = $publicDir . '/ops-ui.css';
$css = is_file($cssFile) ? p7ls_read($cssFile) : '';

if (!str_contains($css, 'P7_OPS_LANGUAGE_SELECTOR_CORE')) {
    $css .= PHP_EOL;
    $css .= '/* P7_OPS_LANGUAGE_SELECTOR_CORE */' . PHP_EOL;
    $css .= '.ops-language-selector{position:fixed;top:18px;right:18px;z-index:1000;display:flex;align-items:center;gap:.45rem;padding:.45rem .55rem;border:1px solid rgba(148,163,184,.28);border-radius:999px;background:rgba(15,23,42,.92);box-shadow:0 12px 30px rgba(0,0,0,.22);backdrop-filter:blur(10px);font-size:.82rem}' . PHP_EOL;
    $css .= '.ops-language-selector__label,.ops-language-selector__active{color:#cbd5e1;white-space:nowrap}' . PHP_EOL;
    $css .= '.ops-language-selector__active{display:none}' . PHP_EOL;
    $css .= '.ops-language-selector__choice{display:inline-flex;align-items:center;justify-content:center;min-width:2.2rem;padding:.25rem .45rem;border-radius:999px;border:1px solid rgba(148,163,184,.35);text-decoration:none;color:#e2e8f0;font-weight:700;letter-spacing:.03em}' . PHP_EOL;
    $css .= '.ops-language-selector__choice.is-active{background:#e2e8f0;color:#0f172a;border-color:#e2e8f0}' . PHP_EOL;
    $css .= '@media (max-width:760px){.ops-language-selector{position:static;margin:1rem auto;max-width:max-content;flex-wrap:wrap;justify-content:center}.ops-language-selector__active{display:inline}}' . PHP_EOL;
}

p7ls_write($cssFile, $css);

$readmeFile = $siteDir . '/README.md';
$readme = is_file($readmeFile) ? p7ls_read($readmeFile) : '# OPUS P7 OPS' . PHP_EOL;

if (!str_contains($readme, 'P7_OPS_LANGUAGE_SELECTOR_CORE')) {
    $readme .= PHP_EOL;
    $readme .= '## P7_OPS_LANGUAGE_SELECTOR_CORE' . PHP_EOL . PHP_EOL;
    $readme .= '- Adds a global FR / EN selector to the OPS pages.' . PHP_EOL;
    $readme .= '- Preserves the current `site` value while switching `lang=fr` / `lang=en`.' . PHP_EOL;
    $readme .= '- Propagates `site` and `lang` to OPS navigation links.' . PHP_EOL;
    $readme .= '- Provides explicit translation helpers for navigation labels.' . PHP_EOL;
    $readme .= '- Covered by `tools/smokes/smoke_p7_ops_language_selector_core.php`.' . PHP_EOL;
}

p7ls_write($readmeFile, $readme);

echo 'P7_OPS_LANGUAGE_SELECTOR_CORE_UPDATED' . PHP_EOL;
