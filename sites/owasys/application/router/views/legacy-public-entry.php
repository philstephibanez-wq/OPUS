<?php
declare(strict_types=1);

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE OPUS_MANAGER_SIGNIN_ROUTE_SMOKE_FIX_CORE OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE */

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

use Opus\Manager\Controller\LogoutController;
use Opus\Manager\Controller\SignInController;
use Opus\Manager\Service\OpusManagerAuth;
use Opus\Manager\Service\OpusManagerEnvironment;
use Opus\Manager\Service\OpusManagerI18n;
use Opus\Manager\Service\OpusManagerModuleRegistry;

$path = rawurldecode((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/opus-manager'), PHP_URL_PATH) ?: '/opus-manager'));
$lang = OpusManagerI18n::resolveLang((string) ($_GET['lang'] ?? 'fr'));
$env = OpusManagerEnvironment::current();

OpusManagerEnvironment::filterProfilerInput($env);

if ($path === '/' || $path === '/opus-manager/') {
    header('Location: /opus-manager/create-site?lang=' . rawurlencode($lang), true, 302);
    return;
}

if ($path === '/opus-manager-ui.css') {
    return false;
}

if ($path === '/opus-manager/login' || $path === '/opus-manager/signin') {
    header('Location: /opus-manager/sign-in?lang=' . rawurlencode($lang), true, 302);
    return;
}

if ($path === '/opus-manager/sign-in') {
    echo (new SignInController())->render(['lang' => $lang, 'env' => $env]);
    return;
}

if ($path === '/opus-manager/logout') {
    echo (new LogoutController())->render(['lang' => $lang, 'env' => $env]);
    return;
}

if (!OpusManagerAuth::isSignedIn()) {
    header('Location: /opus-manager/sign-in?lang=' . rawurlencode($lang) . '&next=' . rawurlencode($path), true, 302);
    return;
}

$routeMap = OpusManagerModuleRegistry::routeMap();
$controllerClass = $routeMap[$path] ?? null;

if ($controllerClass === null) {
    http_response_code(404);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>OPUS Manager — 404</title><link rel="stylesheet" href="/opus-manager-ui.css"></head><body><main class="om-content"><section class="om-card"><h1>Page OPUS Manager introuvable</h1><p>La route demandée n’est pas déclarée dans le shell.</p><p><a href="/opus-manager/create-site?lang=' . htmlspecialchars($lang, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Retour au wizard Créer un site</a></p></section></main></body></html>';
    return;
}

$fqcn = 'Opus\\Manager\\Controller\\' . $controllerClass;
$controller = new $fqcn();

echo $controller->render([
    'lang' => $lang,
    'env' => $env,
    'signed_in' => true,
    'user' => OpusManagerAuth::user(),
]);