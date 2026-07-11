<?php
declare(strict_types=1);

/**
 * OWASYS public entry.
 *
 * Standard OPUS site entry for the OWASYS application.
 * It renders data-only view-models stored in application/<controller>/views/index.php.
 */

$siteRoot = dirname(__DIR__);
$configFile = $siteRoot . '/config/routes.json';
$routesConfig = json_decode((string) file_get_contents($configFile), true);
if (!is_array($routesConfig) || !isset($routesConfig['routes']) || !is_array($routesConfig['routes'])) {
    http_response_code(500);
    echo 'OWASYS_ROUTES_CONFIG_INVALID';
    exit;
}

$h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';
$requestPath = '/' . trim($requestPath, '/');

if ($requestPath === '/') {
    $path = '/';
    $mount = '';
} elseif ($requestPath === '/owasys') {
    $path = '/';
    $mount = '/owasys';
} elseif (str_starts_with($requestPath, '/owasys/')) {
    $path = substr($requestPath, strlen('/owasys'));
    $path = $path === '' ? '/' : $path;
    $mount = '/owasys';
} else {
    $path = $requestPath;
    $mount = '';
}

$route = null;
foreach ($routesConfig['routes'] as $candidate) {
    if (is_array($candidate) && ($candidate['path'] ?? null) === $path) {
        $route = $candidate;
        break;
    }
}
if (!is_array($route)) {
    http_response_code(404);
    echo 'OWASYS_ROUTE_NOT_FOUND: ' . $h($path);
    exit;
}

$controller = (string) ($route['controller'] ?? '');
if (!preg_match('/^[a-z0-9_-]+$/', $controller)) {
    http_response_code(500);
    echo 'OWASYS_CONTROLLER_INVALID';
    exit;
}

$viewFile = $siteRoot . '/application/' . $controller . '/views/index.php';
if (!is_file($viewFile)) {
    http_response_code(500);
    echo 'OWASYS_VIEW_MISSING: ' . $h($controller);
    exit;
}

$page = require $viewFile;
if (!is_array($page)) {
    http_response_code(500);
    echo 'OWASYS_VIEW_MODEL_INVALID';
    exit;
}

$menu = [];
foreach ($routesConfig['routes'] as $candidate) {
    if (is_array($candidate) && ($candidate['show_in_menu'] ?? false) === true) {
        $menu[] = $candidate;
    }
}
usort($menu, static fn (array $a, array $b): int => ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0)));

$asset = static fn (string $assetPath): string => $mount . '/' . ltrim($assetPath, '/');
$link = static fn (string $routePath): string => $mount . ($routePath === '/' ? '/' : $routePath);

$labelMap = [
    'home' => 'Home',
    'applications' => 'Applications',
    'structure' => 'Structure',
    'data' => 'Data Sources',
    'workflows' => 'Workflows',
    'security' => 'Security',
    'build' => 'Build & Validate',
];

$renderList = static function (array $items) use ($h): string {
    if ($items === []) {
        return '';
    }
    $html = '<ul>';
    foreach ($items as $item) {
        $html .= '<li>' . $h((string) $item) . '</li>';
    }
    return $html . '</ul>';
};

$body = '<div class="ow-shell">';
$body .= '<aside class="ow-sidebar">';
$body .= '<div class="ow-brand"><strong>OWASYS</strong><span>OPUS Web Application System</span></div>';
$body .= '<nav class="ow-nav">';
foreach ($menu as $item) {
    $labelKey = str_replace('menu.', '', (string) ($item['label'] ?? ''));
    $label = $labelMap[$labelKey] ?? ucwords(str_replace('-', ' ', $labelKey));
    $active = (($item['path'] ?? '') === $path) ? ' aria-current="page"' : '';
    $body .= '<a' . $active . ' href="' . $h($link((string) ($item['path'] ?? '#'))) . '">' . $h($label) . '</a>';
}
$body .= '</nav>';
$body .= '</aside>';

$body .= '<main class="ow-main">';
$body .= '<header class="ow-topbar">';
$body .= '<div><span class="ow-pill">' . $h((string) ($page['badge'] ?? 'OWASYS')) . '</span>';
$body .= '<h1>' . $h((string) ($page['title'] ?? 'OWASYS')) . '</h1>';
$body .= '<p class="ow-muted">' . $h((string) ($page['summary'] ?? '')) . '</p></div>';
$body .= '</header>';

$cards = (array) ($page['cards'] ?? []);
if ($cards !== []) {
    $body .= '<section class="ow-grid">';
    foreach ($cards as $card) {
        if (!is_array($card)) {
            continue;
        }
        $body .= '<article class="ow-card">';
        $body .= '<h2>' . $h((string) ($card['title'] ?? '')) . '</h2>';
        $body .= '<p>' . $h((string) ($card['body'] ?? '')) . '</p>';
        $body .= $renderList((array) ($card['items'] ?? []));
        $body .= '</article>';
    }
    $body .= '</section>';
} else {
    $body .= '<section class="ow-grid">';
    foreach ((array) ($page['sections'] ?? []) as $section) {
        $body .= '<article class="ow-card"><h2>' . $h((string) $section) . '</h2><p class="ow-muted">Configuration through standard OPUS application folders, models, ODBC datasources and validation contracts.</p></article>';
    }
    $body .= '</section>';
}

if (!empty($page['contracts'])) {
    $body .= '<section class="ow-card"><h2>Contracts</h2><div class="ow-tags">';
    foreach ((array) $page['contracts'] as $contract) {
        $body .= '<span>' . $h((string) $contract) . '</span>';
    }
    $body .= '</div></section>';
}

if (!empty($page['actions'])) {
    $body .= '<section class="ow-card"><h2>Next actions</h2>';
    $body .= $renderList((array) $page['actions']);
    $body .= '</section>';
}

$body .= '</main></div>';

echo '<!doctype html>'
    . '<html lang="fr">'
    . '<head>'
    . '<meta charset="utf-8">'
    . '<meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<title>' . $h((string) ($page['title'] ?? 'OWASYS')) . ' — OWASYS</title>'
    . '<link rel="stylesheet" href="' . $h($asset('/asset/css/owasys.css')) . '">'
    . '<link rel="stylesheet" href="' . $h($asset('/asset/themes/owasys/css/theme.css')) . '">'
    . '</head>'
    . '<body>' . $body
    . '<script src="' . $h($asset('/asset/js/owasys.js')) . '"></script>'
    . '<script src="' . $h($asset('/asset/themes/owasys/js/theme.js')) . '"></script>'
    . '</body></html>';
