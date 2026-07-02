<?php
declare(strict_types=1);

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */

spl_autoload_register(static function (string $class): void {
    $prefix = 'Opus\\Manager\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

use Opus\Manager\Service\OpusManagerModuleRegistry;

$path = rawurldecode((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/opus-manager'), PHP_URL_PATH) ?: '/opus-manager'));

if ($path === '/' || $path === '/opus-manager/') {
    header('Location: /opus-manager/create-site', true, 302);
    return;
}

if ($path === '/opus-manager-ui.css') {
    return false;
}

$routeMap = OpusManagerModuleRegistry::routeMap();
$controllerClass = $routeMap[$path] ?? null;

if ($controllerClass === null) {
    http_response_code(404);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>OPUS Manager — 404</title><link rel="stylesheet" href="/opus-manager-ui.css"></head><body><main class="om-content"><section class="om-card"><h1>Page OPUS Manager introuvable</h1><p>La route demandée n’est pas déclarée dans le shell.</p><p><a href="/opus-manager/create-site">Retour au wizard Créer un site</a></p></section></main></body></html>';
    return;
}

$fqcn = 'Opus\\Manager\\Controller\\' . $controllerClass;
$controller = new $fqcn();

$env = (string) ($_ENV['OPUS_ENV'] ?? getenv('OPUS_ENV') ?: 'dev');
if ($env === 'prod' && (isset($_GET['profiler']) || isset($_GET['_profiler']) || isset($_GET['profile']))) {
    unset($_GET['profiler'], $_GET['_profiler'], $_GET['profile']);
}

echo $controller->render([
    'lang' => (string) ($_GET['lang'] ?? 'fr'),
    'env' => $env,
]);