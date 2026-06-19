<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$siteRoot = dirname(__DIR__);

/**
 * Minimal generated starter front controller.
 *
 * Contract:
 * - resolves declared routes only;
 * - renders .score templates only;
 * - generates route-based menu/rubric projections through .score partials;
 * - keeps the current locale across all starter navigation links;
 * - contains no project-specific business logic;
 * - exists so a generated site is immediately visible and self-documented.
 */
function opus_read_json(string $path): array
{
    if (!is_file($path)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'OPUS_STARTER_REQUIRED_FILE_MISSING: ' . $path;
        exit;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'OPUS_STARTER_JSON_INVALID: ' . $path;
        exit;
    }

    return $decoded;
}

function opus_get(array $data, string $key, bool $raw = false): string
{
    $cursor = $data;
    foreach (explode('.', $key) as $part) {
        if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
            return '';
        }
        $cursor = $cursor[$part];
    }

    if (is_scalar($cursor)) {
        $value = (string) $cursor;
        return $raw ? $value : htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    return '';
}

function opus_render_score(string $template, array $data, array $rawKeys = []): string
{
    return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', static function (array $match) use ($data, $rawKeys): string {
        $key = $match[1];
        return opus_get($data, $key, in_array($key, $rawKeys, true));
    }, $template);
}

function opus_i18n(array $i18n, string $key): string
{
    return is_scalar($i18n[$key] ?? null) ? (string) $i18n[$key] : $key;
}

function opus_route_url(string $path, string $lang): string
{
    return $path . (str_contains($path, '?') ? '&' : '?') . 'lang=' . rawurlencode($lang);
}

function opus_locale_label(array $i18n, string $locale): string
{
    // Language options are always shown as autonyms, not translated into the
    // current UI language, so a user can recognize their language even when the
    // page is currently displayed in an unfamiliar locale.
    $nativeLabels = [
        'fr' => 'Français',
        'en' => 'English',
        'de' => 'Deutsch',
        'es' => 'Español',
        'it' => 'Italiano',
        'pl' => 'Polski',
        'uk' => 'Українська',
        'cs' => 'Čeština',
    ];

    return $nativeLabels[$locale] ?? strtoupper($locale);
}

$siteConfig = opus_read_json($siteRoot . '/application/config/site.json');
$routesConfig = opus_read_json($siteRoot . '/application/config/routes.json');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$route = null;
foreach (($routesConfig['routes'] ?? []) as $candidate) {
    if (($candidate['path'] ?? null) === $path) {
        $route = $candidate;
        break;
    }
}

if (!is_array($route)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'OPUS_STARTER_ROUTE_NOT_FOUND';
    exit;
}

$locales = $siteConfig['locales'] ?? [];
if (!is_array($locales)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'OPUS_STARTER_LOCALES_CONTRACT_INVALID';
    exit;
}

$defaultLocale = (string) ($siteConfig['default_locale'] ?? 'fr');
$queryLocale = isset($_GET['lang']) ? strtolower((string) $_GET['lang']) : '';
$cookieLocale = isset($_COOKIE['opus_starter_lang']) ? strtolower((string) $_COOKIE['opus_starter_lang']) : '';
$lang = $defaultLocale;
if ($queryLocale !== '') {
    $lang = $queryLocale;
} elseif ($cookieLocale !== '' && in_array($cookieLocale, $locales, true)) {
    $lang = $cookieLocale;
}

if (!in_array($lang, $locales, true)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'OPUS_STARTER_LOCALE_UNAVAILABLE';
    exit;
}

if ($queryLocale !== '') {
    setcookie('opus_starter_lang', $lang, [
        'expires' => time() + 31536000,
        'path' => '/',
        'samesite' => 'Lax',
    ]);
}

$i18n = opus_read_json($siteRoot . '/resources/i18n/' . $lang . '.json');
$contentPath = str_replace('{{lang}}', $lang, (string) ($route['content'] ?? ''));
$page = opus_read_json($siteRoot . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $contentPath));

$templatePath = $siteRoot . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $route['template']);
$layoutPath = $siteRoot . '/application/common/templates/layout.score';
$headerPath = $siteRoot . '/application/common/templates/components/header.score';
$footerPath = $siteRoot . '/application/common/templates/components/footer.score';
$poweredPath = $siteRoot . '/application/common/templates/components/powered-by-opus.score';
$menuItemPath = $siteRoot . '/application/common/templates/components/menu-item.score';
$languageSelectorPath = $siteRoot . '/application/common/templates/components/language-selector.score';
$rubricCardPath = $siteRoot . '/application/common/templates/components/rubric-card.score';

$routeUrls = [];
foreach (($routesConfig['routes'] ?? []) as $configuredRoute) {
    if (!is_array($configuredRoute)) {
        continue;
    }
    $moduleKey = strtolower((string) ($configuredRoute['module'] ?? ''));
    $routeUrls[$moduleKey] = opus_route_url((string) ($configuredRoute['path'] ?? '/'), $lang);
}

$pageData = [
    'lang' => $lang,
    'request' => [
        'path' => $path,
    ],
    'routes' => $routeUrls,
    'site' => [
        'id' => (string) ($siteConfig['site_id'] ?? ''),
        'name' => (string) ($siteConfig['site_name'] ?? ''),
        'framework' => 'OPUS',
        'copyright' => '© Log&Play / OPUS — Tous droits réservés',
    ],
    'page' => $page,
    'i18n' => $i18n,
    'common' => [],
    'home' => [],
];

$menuTemplate = (string) file_get_contents($menuItemPath);
$menuHtml = '';
foreach (($routesConfig['routes'] ?? []) as $menuRoute) {
    if (($menuRoute['show_in_menu'] ?? false) !== true) {
        continue;
    }
    $menuData = $pageData;
    $menuData['menu_item'] = [
        'path' => opus_route_url((string) ($menuRoute['path'] ?? '#'), $lang),
        'label' => opus_i18n($i18n, (string) ($menuRoute['label'] ?? '')),
        'active_class' => (($menuRoute['id'] ?? '') === ($route['id'] ?? '')) ? 'opus-nav__link--active' : '',
    ];
    $menuHtml .= opus_render_score($menuTemplate, $menuData);
}
$pageData['common']['menu'] = $menuHtml;

$languageOptions = '';
foreach ($locales as $locale) {
    if (!is_scalar($locale)) {
        continue;
    }
    $localeValue = (string) $locale;
    $selected = $localeValue === $lang ? ' selected' : '';
    $languageOptions .= '<option value="' . htmlspecialchars($localeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' . $selected . '>'
        . htmlspecialchars(opus_locale_label($i18n, $localeValue), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</option>';
}
$pageData['common']['language_options'] = $languageOptions;
$pageData['common']['language_selector'] = opus_render_score((string) file_get_contents($languageSelectorPath), $pageData, ['common.language_options']);

$rubricTemplate = (string) file_get_contents($rubricCardPath);
$rubricCards = '';
foreach (($routesConfig['routes'] ?? []) as $rubricRoute) {
    if (($rubricRoute['show_on_home'] ?? false) !== true) {
        continue;
    }
    $rubricContentPath = str_replace('{{lang}}', $lang, (string) ($rubricRoute['content'] ?? ''));
    $rubricPage = opus_read_json($siteRoot . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rubricContentPath));
    $rubricData = $pageData;
    $rubricData['rubric'] = [
        'path' => opus_route_url((string) ($rubricRoute['path'] ?? '#'), $lang),
        'module' => (string) ($rubricRoute['module'] ?? ''),
        'kicker' => (string) ($rubricPage['kicker'] ?? ''),
        'title' => (string) ($rubricPage['title'] ?? ''),
        'description' => (string) ($rubricPage['description'] ?? $rubricPage['subtitle'] ?? ''),
    ];
    $rubricCards .= opus_render_score($rubricTemplate, $rubricData);
}
$pageData['home']['rubric_cards'] = $rubricCards;
$pageData['common']['powered_by'] = opus_render_score((string) file_get_contents($poweredPath), $pageData);

$content = opus_render_score((string) file_get_contents($templatePath), $pageData, ['common.menu', 'home.rubric_cards', 'common.powered_by']);
$pageData['content'] = $content;
$pageData['common']['header'] = opus_render_score((string) file_get_contents($headerPath), $pageData, ['common.menu', 'common.language_selector']);
$pageData['common']['footer'] = opus_render_score((string) file_get_contents($footerPath), $pageData, ['common.powered_by']);

header('Content-Type: text/html; charset=UTF-8');
echo opus_render_score((string) file_get_contents($layoutPath), $pageData, ['common.header', 'common.footer', 'content']);