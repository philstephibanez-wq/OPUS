<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';

$siteFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json';
$routesFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes.json';
$securityFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'security-policy.json';
$frontFile = $siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'index.php';
$cssFile = $siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'asset' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'owasys.css';
$loginView = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'login' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'index.php';

$site = json_decode((string) file_get_contents($siteFile), true);
$routes = json_decode((string) file_get_contents($routesFile), true);
$security = json_decode((string) file_get_contents($securityFile), true);

if (!is_array($site) || !is_array($routes) || !is_array($security)) {
    fwrite(STDERR, "OWASYS_LOGIN_FOUNDATION_CONFIG_INVALID\n");
    exit(1);
}

if (($site['auth']['session_name'] ?? null) !== 'OWASYS_LOCAL_SESSION') {
    fwrite(STDERR, "OWASYS_LOGIN_FOUNDATION_SESSION_NAME_INVALID\n");
    exit(1);
}

if (($site['auth']['committed_passwords_allowed'] ?? null) !== false) {
    fwrite(STDERR, "OWASYS_LOGIN_FOUNDATION_COMMITTED_PASSWORDS_NOT_FORBIDDEN\n");
    exit(1);
}

$roots = $site['application_roots'] ?? [];
if (!is_array($roots) || !in_array('login', $roots, true)) {
    fwrite(STDERR, "OWASYS_LOGIN_FOUNDATION_ROOT_MISSING\n");
    exit(1);
}

if (($security['session_strategy'] ?? null) !== 'explicit') {
    fwrite(STDERR, "OWASYS_LOGIN_FOUNDATION_SECURITY_SESSION_STRATEGY_INVALID\n");
    exit(1);
}

if (($security['local_bootstrap']['profile'] ?? null) !== 'dev') {
    fwrite(STDERR, "OWASYS_LOGIN_FOUNDATION_LOCAL_BOOTSTRAP_PROFILE_INVALID\n");
    exit(1);
}

$loginRoute = null;
foreach ((array) ($routes['routes'] ?? []) as $route) {
    if (is_array($route) && ($route['path'] ?? null) === '/login') {
        $loginRoute = $route;
        break;
    }
}

if (!is_array($loginRoute)) {
    fwrite(STDERR, "OWASYS_LOGIN_FOUNDATION_ROUTE_MISSING\n");
    exit(1);
}

if (($loginRoute['controller'] ?? null) !== 'login' || ($loginRoute['show_in_menu'] ?? null) !== false) {
    fwrite(STDERR, "OWASYS_LOGIN_FOUNDATION_ROUTE_INVALID\n");
    exit(1);
}

if (!is_file($loginView)) {
    fwrite(STDERR, "OWASYS_LOGIN_FOUNDATION_VIEW_MISSING\n");
    exit(1);
}

$front = (string) file_get_contents($frontFile);
foreach ([
    'session_name($sessionName)',
    'local-dev-signin',
    'OWASYS_LOGIN_ACTION_INVALID',
    'owasys_user',
    "'/logout'",
] as $needle) {
    if (!str_contains($front, $needle)) {
        fwrite(STDERR, "OWASYS_LOGIN_FOUNDATION_FRONT_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

$css = (string) file_get_contents($cssFile);
foreach (['.ow-auth-status', '.ow-auth-panel', '.ow-login-form', '.ow-button'] as $needle) {
    if (!str_contains($css, $needle)) {
        fwrite(STDERR, "OWASYS_LOGIN_FOUNDATION_CSS_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

$loginSource = (string) file_get_contents($loginView);
foreach (['committed_passwords_allowed', 'local-dev-bootstrap', 'profile'] as $needle) {
    if (!str_contains($loginSource, $needle)) {
        fwrite(STDERR, "OWASYS_LOGIN_FOUNDATION_VIEW_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

echo "OWASYS_LOGIN_FOUNDATION_SMOKE_OK\n";
