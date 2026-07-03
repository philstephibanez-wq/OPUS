<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$siteRoot = dirname(__DIR__);
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$path = '/' . trim($path, '/');
if ($path === '/') {
    $path = '/opus-manager';
}

$configPath = $siteRoot . '/config/routes.json';
$routes = is_file($configPath) ? json_decode((string) file_get_contents($configPath), true) : [];
$route = null;
foreach (($routes['routes'] ?? []) as $candidate) {
    if (is_array($candidate) && ($candidate['path'] ?? null) === $path) {
        $route = $candidate;
        break;
    }
}
if (!is_array($route)) {
    $controller = preg_replace('/\.php$/', '', basename($path)) ?: 'home';
    foreach (($routes['routes'] ?? []) as $candidate) {
        if (is_array($candidate) && ($candidate['controller'] ?? null) === $controller) {
            $route = $candidate;
            break;
        }
    }
}
if (!is_array($route)) {
    http_response_code(404);
    echo 'OPUS_ROUTE_NOT_FOUND';
    exit;
}
$class = $route['class'] ?? null;
if (is_string($class) && $class !== '' && class_exists($class)) {
    $instance = new $class();
    echo $instance->render(['route' => $route]);
    exit;
}
$controller = (string) ($route['controller'] ?? 'home');
$legacy = $siteRoot . '/application/' . $controller . '/views/legacy-public-entry.php';
if (is_file($legacy)) {
    require $legacy;
    exit;
}
$template = $siteRoot . '/' . (string) ($route['template'] ?? '');
if (is_file($template)) {
    $title = htmlspecialchars((string) ($route['label'] ?? $controller), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>' . $title . '</title><link rel="stylesheet" href="/asset/themes/starter/css/theme.css"><link rel="stylesheet" href="/asset/css/default.css"></head><body class="opus-manager-site"><main class="opus-manager-card"><h1>' . $title . '</h1><p>OPUS Manager controller: ' . htmlspecialchars($controller, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p></main></body></html>';
    exit;
}
http_response_code(500);
echo 'OPUS_MANAGER_CONTROLLER_TARGET_MISSING';