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
$siteFile = $siteRoot . '/config/site.json';
$routesConfig = json_decode((string) file_get_contents($configFile), true);
$siteConfig = json_decode((string) file_get_contents($siteFile), true);
if (!is_array($routesConfig) || !isset($routesConfig['routes']) || !is_array($routesConfig['routes'])) {
    http_response_code(500);
    echo 'OWASYS_ROUTES_CONFIG_INVALID';
    exit;
}
if (!is_array($siteConfig)) {
    http_response_code(500);
    echo 'OWASYS_SITE_CONFIG_INVALID';
    exit;
}

$authConfig = is_array($siteConfig['auth'] ?? null) ? $siteConfig['auth'] : [];
$sessionName = (string) ($authConfig['session_name'] ?? 'OWASYS_LOCAL_SESSION');
if (preg_match('/^[A-Za-z0-9_-]+$/', $sessionName) !== 1) {
    http_response_code(500);
    echo 'OWASYS_SESSION_NAME_INVALID';
    exit;
}
if (session_status() === PHP_SESSION_NONE) {
    session_name($sessionName);
    session_start();
}

$h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$authStoreRelative = trim(str_replace('\\', '/', (string) ($authConfig['user_store'] ?? 'var/auth/local-users.json')), '/');
if ($authStoreRelative === '' || str_contains($authStoreRelative, '..')) {
    http_response_code(500);
    echo 'OWASYS_AUTH_USER_STORE_PATH_INVALID';
    exit;
}
$authStoreFile = $siteRoot . '/' . $authStoreRelative;

$loadRuntimeUsers = static function (string $storeFile): array {
    if (!is_file($storeFile)) {
        return [];
    }
    $store = json_decode((string) file_get_contents($storeFile), true);
    if (!is_array($store) || ($store['contract'] ?? null) !== 'OWASYS_LOCAL_USER_STORE_V1') {
        return [];
    }
    $users = $store['users'] ?? [];
    return is_array($users) ? $users : [];
};

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

$link = static fn (string $routePath): string => $mount . ($routePath === '/' ? '/' : $routePath);
$redirect = static function (string $routePath) use ($link): void {
    header('Location: ' . $link($routePath), true, 303);
    exit;
};

$loginError = null;

if ($path === '/logout') {
    unset($_SESSION['owasys_user']);
    session_regenerate_id(true);
    $redirect('/login');
}

if ($path === '/login' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['owasys_action'] ?? '');
    if ($action !== 'password-signin') {
        http_response_code(400);
        echo 'OWASYS_LOGIN_ACTION_INVALID';
        exit;
    }

    $username = trim((string) ($_POST['owasys_username'] ?? ''));
    $password = (string) ($_POST['owasys_password'] ?? '');
    if ($username === '' || $password === '') {
        $loginError = 'Username and password are required.';
    } else {
        $users = $loadRuntimeUsers($authStoreFile);
        $candidate = is_array($users[$username] ?? null) ? $users[$username] : null;
        $passwordHash = is_array($candidate) ? (string) ($candidate['password_hash'] ?? '') : '';
        if ($candidate === null || $passwordHash === '' || !password_verify($password, $passwordHash)) {
            $loginError = 'Invalid username or password.';
        } else {
            session_regenerate_id(true);
            $_SESSION['owasys_user'] = [
                'id' => (string) ($candidate['id'] ?? $username),
                'label' => (string) ($candidate['label'] ?? $username),
                'profile' => (string) ($candidate['profile'] ?? 'dev'),
                'mode' => 'runtime-password-store',
                'started_at' => gmdate('c'),
            ];
            $redirect('/');
        }
    }
}

$user = is_array($_SESSION['owasys_user'] ?? null) ? $_SESSION['owasys_user'] : null;
$isAuthenticated = is_array($user);

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

$labelMap = [
    'home' => 'Home',
    'applications' => 'Applications',
    'structure' => 'Structure',
    'data' => 'Data Sources',
    'workflows' => 'Workflows',
    'security' => 'Security',
    'build' => 'Build & Validate',
    'login' => 'Login',
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
$body .= '<div class="ow-auth-status">';
if ($isAuthenticated) {
    $body .= '<span class="ow-auth-dot" aria-hidden="true"></span><strong>' . $h((string) ($user['label'] ?? 'User')) . '</strong>';
    $body .= '<small>profile: ' . $h((string) ($user['profile'] ?? 'unknown')) . '</small>';
    $body .= '<a href="' . $h($link('/logout')) . '">Logout</a>';
} else {
    $body .= '<span class="ow-auth-dot is-off" aria-hidden="true"></span><strong>Not signed in</strong>';
    $body .= '<small>password session inactive</small>';
    $body .= '<a href="' . $h($link('/login')) . '">Login</a>';
}
$body .= '</div>';
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

if ($controller === 'login') {
    $body .= '<section class="ow-card ow-auth-panel">';
    if ($isAuthenticated) {
        $body .= '<h2>Session active</h2>';
        $body .= '<p>You are signed in for this local OWASYS session.</p>';
        $body .= '<div class="ow-tags"><span>profile: ' . $h((string) ($user['profile'] ?? 'unknown')) . '</span><span>mode: ' . $h((string) ($user['mode'] ?? 'unknown')) . '</span></div>';
        $body .= '<p><a class="ow-button" href="' . $h($link('/logout')) . '">Logout</a><a class="ow-button ow-button-secondary" href="' . $h($link('/')) . '">Dashboard</a></p>';
    } else {
        $body .= '<h2>Sign in</h2>';
        $body .= '<p>Use a runtime local OWASYS user. Credentials are generated locally and are not committed to Git.</p>';
        if ($loginError !== null) {
            $body .= '<p class="ow-login-error">' . $h($loginError) . '</p>';
        }
        if (!is_file($authStoreFile)) {
            $body .= '<p class="ow-login-warning">Runtime user store missing. Run <code>php tools\\owasys_auth_bootstrap_local_user.php</code> from the OPUS root.</p>';
        }
        $body .= '<form method="post" class="ow-login-form">';
        $body .= '<input type="hidden" name="owasys_action" value="password-signin">';
        $body .= '<label>Username<input name="owasys_username" autocomplete="username" required></label>';
        $body .= '<label>Password<input name="owasys_password" type="password" autocomplete="current-password" required></label>';
        $body .= '<button class="ow-button" type="submit">Sign in</button>';
        $body .= '</form>';
    }
    $body .= '</section>';
}

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
