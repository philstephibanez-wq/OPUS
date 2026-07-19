<?php
declare(strict_types=1);

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';
$siteRoot = dirname(__DIR__);

if ($requestPath === '/i18n.php' || $requestPath === '/owasys/i18n.php') {
    $siteConfig = json_decode((string) file_get_contents($siteRoot . '/config/site.json'), true);
    $locales = array_values(array_filter((array) ($siteConfig['locales'] ?? ['fr']), 'is_string'));
    $defaultLocale = in_array((string) ($siteConfig['default_locale'] ?? 'fr'), $locales, true)
        ? (string) ($siteConfig['default_locale'] ?? 'fr')
        : 'fr';
    $requested = strtolower((string) ($_GET['lang'] ?? $defaultLocale));
    $locale = in_array($requested, $locales, true) ? $requested : $defaultLocale;
    $catalogFile = $siteRoot . '/application/default/local/' . $locale . '.php';
    $messages = is_file($catalogFile) ? require $catalogFile : [];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'contract' => 'OWASYS_I18N_CATALOG_V1',
        'locale' => $locale,
        'messages' => is_array($messages) ? $messages : [],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return;
}

if ($requestPath === '/source-action.php' || $requestPath === '/owasys/source-action.php') {
    $siteConfig = json_decode((string) file_get_contents($siteRoot . '/config/site.json'), true);
    $authConfig = is_array($siteConfig['auth'] ?? null) ? $siteConfig['auth'] : [];
    $sessionName = (string) ($authConfig['session_name'] ?? 'OWASYS_LOCAL_SESSION');
    if (session_status() === PHP_SESSION_NONE) {
        session_name($sessionName);
        session_start();
    }
    require $siteRoot . '/application/states/source/actions/source-browser-action.php';
    return;
}

if (PHP_SAPI === 'cli-server' && $requestPath !== '/' && !str_contains($requestPath, "\0")) {
    $publicRoot = __DIR__;
    $candidate = realpath($publicRoot . '/' . ltrim($requestPath, '/'));
    $publicRootReal = realpath($publicRoot);

    if (
        is_string($candidate)
        && is_string($publicRootReal)
        && str_starts_with($candidate, $publicRootReal . DIRECTORY_SEPARATOR)
        && is_file($candidate)
        && strtolower(pathinfo($candidate, PATHINFO_EXTENSION)) !== 'php'
    ) {
        return false;
    }
}

ob_start();
require __DIR__ . '/application.php';
$html = (string) ob_get_clean();
$mount = str_starts_with($requestPath, '/owasys/') || $requestPath === '/owasys' ? '/owasys' : '';
$script = '<script defer src="' . $mount . '/asset/js/i18n-ui.js?v=1"></script>';
if (str_contains($html, '</body>')) {
    $html = str_replace('</body>', $script . '</body>', $html);
} else {
    $html .= $script;
}
echo $html;
