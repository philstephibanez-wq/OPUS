<?php
/** P7_OPS_PROFILER_OPEN_CONTEXT_CORE */
/** P7_OPS_PROFILER_VISIBLE_MODE_CORE */
/** P7_OPS_PROFILER_EXIT_FIX_CORE */
/** P7_OPS_UNIFIED_ERGONOMIC_NAVIGATION_CORE */
/** P7_OPS_PROFILER_CHAIN_CLEANUP_CORE */
declare(strict_types=1);

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$decodedPath = rawurldecode($rawPath);
$path = $decodedPath === '/' ? '/' : rtrim($decodedPath, '/');

$staticFile = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);
if ($path !== '/' && is_file($staticFile)) {
    return false;
}
if ($path !== '/' && preg_match('~\.(?:ico|png|jpe?g|gif|svg|webp|css|js|map|woff2?|ttf|eot)$~i', $path)) {
    http_response_code(404);
    return true;
}

require_once __DIR__ . '/language.php';

p7ops_access_log_once();
if (!p7ops_profiler_exit_path($path)) {
    p7ops_clean_profiler_boot_once();
}
if (!p7ops_profiler_exit_path($path)) {
    p7ops_unified_navigation_boot_once();
p7ops_profiler_visible_boot_once();
p7ops_profiler_context_store_app_uri();
}

$nativeRoute = p7ops_resolve_native_route($path);
if ($nativeRoute !== null) {
    $_GET['lang'] = (string) $nativeRoute['lang'];
    $_GET['site'] = $_GET['site'] ?? 'site-alpha';
    $path = (string) $nativeRoute['canonical'];
}

$publicRoutes = [
    '/opus-lstsar-manager/login' => 'login.php',
    '/login' => 'login.php',
    '/opus-lstsar-manager/signin' => 'login.php',
    '/opus-lstsar-manager/sign-in' => 'login.php',
    '/opus-lstsar-manager/logout' => 'logout.php',
    '/logout' => 'logout.php',
    '/opus-lstsar-manager/profiler' => 'profiler.php',
    '/opus-lstsar-manager/profiler/exit' => 'profiler-exit.php',
    '/_profiler' => 'profiler.php',
    '/_profiler/exit' => 'profiler-exit.php',
];

if (isset($publicRoutes[$path])) {
    require __DIR__ . '/' . $publicRoutes[$path];
    return true;
}

p7ops_require_signin();

$routes = [
    '/opus-lstsar-manager' => 'index.php',
    '/opus-lstsar-manager/operations' => 'index.php',
    '/opus-lstsar-manager/action' => 'action.php',
    '/opus-lstsar-manager/command' => 'command.php',
    '/opus-lstsar-manager/command-center' => 'command.php',
    '/opus-lstsar-manager/navigation' => 'navigation.php',
    '/opus-lstsar-manager/navigation-polish' => 'navigation.php',
    '/opus-lstsar-manager/diagnostics' => 'diagnostics.php',
    '/opus-lstsar-manager/runtime-diagnostics' => 'diagnostics.php',
    '/opus-lstsar-manager/health' => 'health.php',
    '/opus-lstsar-manager/health-hub' => 'health.php',
    '/opus-lstsar-manager/chain' => 'chain.php',
    '/opus-lstsar-manager/dependency-chain' => 'chain.php',
    '/opus-lstsar-manager/fsm' => 'fsm.php',
    '/opus-lstsar-manager/cl' => 'cl.php',
    '/opus-lstsar-manager/models' => 'models.php',
    '/opus-lstsar-manager/odbc-manager' => 'odbc-manager.php',
    '/opus-lstsar-manager/sso' => 'sso.php',
];

require __DIR__ . '/' . ($routes[$path] ?? 'index.php');
return true;