<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';

$siteFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json';
$routesFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes.json';
$securityFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'security-policy.json';
$frontFile = $siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'index.php';
$cssFile = $siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'asset' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'owasys.css';
$jsFile = $siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'asset' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'owasys.js';
$loginView = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'login' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'index.php';
$accountView = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'account' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'index.php';
$bootstrapTool = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'owasys_auth_bootstrap_local_user.php';
$gitignoreFile = $root . DIRECTORY_SEPARATOR . '.gitignore';
$composerFile = $root . DIRECTORY_SEPARATOR . 'composer.json';

$site = json_decode((string) file_get_contents($siteFile), true);
$routes = json_decode((string) file_get_contents($routesFile), true);
$security = json_decode((string) file_get_contents($securityFile), true);
$composer = json_decode((string) file_get_contents($composerFile), true);
if (!is_array($site) || !is_array($routes) || !is_array($security) || !is_array($composer)) {
    fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_CONFIG_INVALID\n");
    exit(1);
}

foreach ([$frontFile, $loginView, $accountView, $bootstrapTool, $cssFile, $jsFile, $gitignoreFile] as $requiredFile) {
    if (!is_file($requiredFile)) {
        fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_REQUIRED_FILE_MISSING: {$requiredFile}\n");
        exit(1);
    }
}

$auth = is_array($site['auth'] ?? null) ? $site['auth'] : [];
if (($auth['mode'] ?? null) !== 'runtime-password-store') {
    fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_MODE_INVALID\n");
    exit(1);
}

if (($auth['user_store'] ?? null) !== 'var/auth/local-users.json') {
    fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_USER_STORE_INVALID\n");
    exit(1);
}

if (($auth['committed_passwords_allowed'] ?? null) !== false) {
    fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_COMMITTED_PASSWORDS_ALLOWED\n");
    exit(1);
}

if (($auth['protected_routes'] ?? null) !== 'all_except_anonymous_routes') {
    fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_PROTECTED_ROUTES_INVALID\n");
    exit(1);
}

if (($auth['anonymous_routes'] ?? null) !== ['/login']) {
    fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_ANONYMOUS_ROUTES_INVALID\n");
    exit(1);
}

if (($auth['password_change_route'] ?? null) !== '/account/password') {
    fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_CHANGE_ROUTE_INVALID\n");
    exit(1);
}

if (($auth['must_change_password_on_bootstrap'] ?? null) !== true) {
    fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_BOOTSTRAP_MUST_CHANGE_INVALID\n");
    exit(1);
}

if (($auth['minimum_password_length'] ?? null) !== 10) {
    fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_MINIMUM_LENGTH_INVALID\n");
    exit(1);
}

$roots = is_array($site['application_roots'] ?? null) ? $site['application_roots'] : [];
if (!in_array('account', $roots, true)) {
    fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_ACCOUNT_ROOT_MISSING\n");
    exit(1);
}

$accountRoute = null;
foreach ((array) ($routes['routes'] ?? []) as $route) {
    if (is_array($route) && ($route['path'] ?? null) === '/account/password') {
        $accountRoute = $route;
        break;
    }
}
if (!is_array($accountRoute) || ($accountRoute['controller'] ?? null) !== 'account' || ($accountRoute['show_in_menu'] ?? null) !== false) {
    fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_ACCOUNT_ROUTE_INVALID\n");
    exit(1);
}

$localCredentials = is_array($security['local_credentials'] ?? null) ? $security['local_credentials'] : [];
foreach ([
    'store_contract' => 'OWASYS_LOCAL_USER_STORE_V1',
    'store' => 'var/auth/local-users.json',
    'bootstrap_tool' => 'tools/owasys_auth_bootstrap_local_user.php',
] as $field => $expected) {
    if (($localCredentials[$field] ?? null) !== $expected) {
        fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_SECURITY_POLICY_INVALID: {$field}\n");
        exit(1);
    }
}

foreach (['password_hash_required', 'plain_text_passwords_forbidden', 'committed_credentials_forbidden'] as $field) {
    if (($localCredentials[$field] ?? null) !== true) {
        fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_SECURITY_BOOLEAN_INVALID: {$field}\n");
        exit(1);
    }
}

$front = (string) file_get_contents($frontFile);
foreach ([
    'password-signin',
    'owasys_username',
    'owasys_password',
    'Username<input name="owasys_username"',
    'Password<input name="owasys_password" type="password"',
    'password_verify',
    'OWASYS_LOCAL_USER_STORE_V1',
    'Runtime user store missing',
    '$anonymousRoutes = [\'/login\'];',
    'if (!$isAuthenticated && !in_array($path, $anonymousRoutes, true))',
    '$redirect(\'/login\');',
    "'/account/password'",
    'must_change_password',
    'change-password',
    'owasys_current_password',
    'owasys_new_password',
    'owasys_confirm_password',
    'OWASYS_PASSWORD_CHANGE_ACTION_INVALID',
    'New password must contain at least 10 characters.',
    'minlength="10"',
] as $needle) {
    if (!str_contains($front, $needle)) {
        fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_FRONT_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

foreach (['local-dev-signin', 'Start local dev session', 'at least 12 characters', 'minlength="12"'] as $forbidden) {
    if (str_contains($front, $forbidden)) {
        fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_FORBIDDEN_BOOTSTRAP_MARKER_PRESENT: {$forbidden}\n");
        exit(1);
    }
}

$login = (string) file_get_contents($loginView);
foreach (['runtime-password-store', 'username and password', 'OWASYS_LOCAL_USER_STORE_V1'] as $needle) {
    if (!str_contains($login, $needle)) {
        fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_VIEW_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

$account = (string) file_get_contents($accountView);
foreach (['Account password', 'must_change_password', 'OWASYS_LOCAL_USER_STORE_V1', 'at least 10 characters'] as $needle) {
    if (!str_contains($account, $needle)) {
        fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_ACCOUNT_VIEW_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

$css = (string) file_get_contents($cssFile);
foreach (['.ow-login-form input', '.ow-password-form input', '.ow-auth-warning', '.ow-login-error', '.ow-login-warning', '.ow-password-field', '.ow-password-eye'] as $needle) {
    if (!str_contains($css, $needle)) {
        fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_CSS_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

$js = (string) file_get_contents($jsFile);
foreach (['owasysPasswordToggle', 'ow-password-eye', 'Afficher le mot de passe', 'Masquer le mot de passe', "input[type=\"password\"]"] as $needle) {
    if (!str_contains($js, $needle)) {
        fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_JS_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

$bootstrap = (string) file_get_contents($bootstrapTool);
foreach (['password_hash($password, PASSWORD_DEFAULT)', 'OWASYS_LOCAL_USER_STORE_V1', 'OWASYS_AUTH_BOOTSTRAP_USER_OK', 'must_change_password', 'OWASYS_AUTH_BOOTSTRAP_MUST_CHANGE_PASSWORD'] as $needle) {
    if (!str_contains($bootstrap, $needle)) {
        fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_BOOTSTRAP_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

foreach (['ChangeMe', 'password123', 'admin123'] as $forbidden) {
    if (str_contains($bootstrap, $forbidden) || str_contains($front, $forbidden) || str_contains($login, $forbidden) || str_contains($account, $forbidden)) {
        fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_FORBIDDEN_SAMPLE_PASSWORD_PRESENT\n");
        exit(1);
    }
}

$gitignore = (string) file_get_contents($gitignoreFile);
if (!str_contains($gitignore, '/sites/*/var/') || !str_contains($gitignore, '*.sqlite')) {
    fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_GITIGNORE_RUNTIME_STATE_INVALID\n");
    exit(1);
}

$scripts = is_array($composer['scripts'] ?? null) ? $composer['scripts'] : [];
if (($scripts['owasys:auth-bootstrap'] ?? null) !== '@php tools/owasys_auth_bootstrap_local_user.php') {
    fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_COMPOSER_BOOTSTRAP_SCRIPT_MISSING\n");
    exit(1);
}
if (($scripts['owasys:smoke-login-password'] ?? null) !== '@php tools/smoke_owasys_login_password.php') {
    fwrite(STDERR, "OWASYS_LOGIN_PASSWORD_COMPOSER_SMOKE_SCRIPT_MISSING\n");
    exit(1);
}

echo "OWASYS_LOGIN_PASSWORD_SMOKE_OK\n";