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
$mermaidLabel = static function (string $value): string {
    $clean = preg_replace('/[^A-Za-z0-9 _.:\/\-]/', '', $value);
    $clean = trim(is_string($clean) ? $clean : '');
    return $clean === '' ? 'unknown' : $clean;
};

$authStoreRelative = trim(str_replace('\\', '/', (string) ($authConfig['user_store'] ?? 'var/auth/local-users.json')), '/');
if ($authStoreRelative === '' || str_contains($authStoreRelative, '..')) {
    http_response_code(500);
    echo 'OWASYS_AUTH_USER_STORE_PATH_INVALID';
    exit;
}
$authStoreFile = $siteRoot . '/' . $authStoreRelative;

$readRuntimeStore = static function (string $storeFile): array {
    if (!is_file($storeFile)) {
        return ['contract' => 'OWASYS_LOCAL_USER_STORE_V1', 'committed' => false, 'users' => []];
    }
    $store = json_decode((string) file_get_contents($storeFile), true);
    if (!is_array($store) || ($store['contract'] ?? null) !== 'OWASYS_LOCAL_USER_STORE_V1') {
        return ['contract' => 'OWASYS_LOCAL_USER_STORE_V1', 'committed' => false, 'users' => []];
    }
    if (!isset($store['users']) || !is_array($store['users'])) {
        $store['users'] = [];
    }
    return $store;
};

$loadRuntimeUsers = static function (string $storeFile) use ($readRuntimeStore): array {
    $store = $readRuntimeStore($storeFile);
    return is_array($store['users'] ?? null) ? $store['users'] : [];
};

$writeRuntimeStore = static function (string $storeFile, array $store): void {
    $parent = dirname($storeFile);
    if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
        http_response_code(500);
        echo 'OWASYS_AUTH_USER_STORE_DIRECTORY_FAILED';
        exit;
    }
    $store['contract'] = 'OWASYS_LOCAL_USER_STORE_V1';
    $store['committed'] = false;
    $store['updated_at'] = gmdate('c');
    $encoded = json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($encoded) || file_put_contents($storeFile, $encoded . "\n") === false) {
        http_response_code(500);
        echo 'OWASYS_AUTH_USER_STORE_WRITE_FAILED';
        exit;
    }
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
$passwordChangeError = null;
$registryActionError = null;

if ($path === '/logout') {
    unset($_SESSION['owasys_user'], $_SESSION['owasys_current_app']);
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
                'must_change_password' => ($candidate['must_change_password'] ?? false) === true,
                'started_at' => gmdate('c'),
            ];
            $redirect(($_SESSION['owasys_user']['must_change_password'] ?? false) === true ? '/account/password' : '/applications');
        }
    }
}

$user = is_array($_SESSION['owasys_user'] ?? null) ? $_SESSION['owasys_user'] : null;
$isAuthenticated = is_array($user);
$anonymousRoutes = ['/login'];
if (!$isAuthenticated && !in_array($path, $anonymousRoutes, true)) {
    $redirect('/login');
}

if ($isAuthenticated && $path === '/account/password' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['owasys_action'] ?? '');
    if ($action !== 'change-password') {
        http_response_code(400);
        echo 'OWASYS_PASSWORD_CHANGE_ACTION_INVALID';
        exit;
    }

    $currentPassword = (string) ($_POST['owasys_current_password'] ?? '');
    $newPassword = (string) ($_POST['owasys_new_password'] ?? '');
    $confirmPassword = (string) ($_POST['owasys_confirm_password'] ?? '');
    $userId = (string) ($user['id'] ?? '');
    $store = $readRuntimeStore($authStoreFile);
    $users = is_array($store['users'] ?? null) ? $store['users'] : [];
    $candidate = is_array($users[$userId] ?? null) ? $users[$userId] : null;
    $passwordHash = is_array($candidate) ? (string) ($candidate['password_hash'] ?? '') : '';

    if ($candidate === null || $passwordHash === '') {
        $passwordChangeError = 'Runtime user is missing from the local user store.';
    } elseif ($currentPassword === '' || !password_verify($currentPassword, $passwordHash)) {
        $passwordChangeError = 'Current password is invalid.';
    } elseif (strlen($newPassword) < 10) {
        $passwordChangeError = 'New password must contain at least 10 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $passwordChangeError = 'New password confirmation does not match.';
    } elseif (password_verify($newPassword, $passwordHash)) {
        $passwordChangeError = 'New password must be different from the current password.';
    } else {
        $candidate['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $candidate['must_change_password'] = false;
        $candidate['password_changed_at'] = gmdate('c');
        $candidate['updated_at'] = gmdate('c');
        $store['users'][$userId] = $candidate;
        $writeRuntimeStore($authStoreFile, $store);
        $_SESSION['owasys_user']['must_change_password'] = false;
        $_SESSION['owasys_user']['password_changed_at'] = $candidate['password_changed_at'];
        $redirect('/applications');
    }
}

$user = is_array($_SESSION['owasys_user'] ?? null) ? $_SESSION['owasys_user'] : null;
$isAuthenticated = is_array($user);
$mustChangePassword = $isAuthenticated && (($user['must_change_password'] ?? false) === true);
if ($mustChangePassword && !in_array($path, ['/account/password', '/logout'], true)) {
    $redirect('/account/password');
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

$currentApp = is_array($_SESSION['owasys_current_app'] ?? null) ? $_SESSION['owasys_current_app'] : null;
$findRegistryEntry = static function (array $entries, string $id): ?array {
    foreach ($entries as $entry) {
        if (is_array($entry) && (string) ($entry['id'] ?? '') === $id) {
            return $entry;
        }
    }

    return null;
};

if ($controller === 'registry' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['owasys_action'] ?? '');
    if ($action === 'select-app') {
        $appId = trim((string) ($_POST['owasys_app_id'] ?? ''));
        $entry = $findRegistryEntry((array) ($page['registry_entries'] ?? []), $appId);
        if ($entry === null) {
            $registryActionError = 'Selected application is not registered.';
        } else {
            $_SESSION['owasys_current_app'] = $entry;
            $redirect('/structure');
        }
    } elseif ($action === 'clear-app-context') {
        unset($_SESSION['owasys_current_app']);
        $redirect('/applications');
    } elseif ($action === 'create-new-app') {
        unset($_SESSION['owasys_current_app']);
        $redirect('/build');
    } else {
        http_response_code(400);
        echo 'OWASYS_REGISTRY_ACTION_INVALID';
        exit;
    }
}

$currentApp = is_array($_SESSION['owasys_current_app'] ?? null) ? $_SESSION['owasys_current_app'] : null;
$requiresCurrentApp = in_array($path, ['/structure', '/data', '/workflows', '/security'], true);
if ($isAuthenticated && $requiresCurrentApp && $currentApp === null) {
    $redirect('/applications');
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
    'account' => 'Account',
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

$renderAppTags = static function (?array $app) use ($h): string {
    if ($app === null) {
        return '<div class="ow-tags"><span>No current application</span></div>';
    }

    $items = [
        'id: ' . (string) ($app['id'] ?? 'unknown'),
        'type: ' . (string) ($app['kind'] ?? 'unknown'),
        'root: ' . (string) ($app['root_path'] ?? 'unknown'),
        'status: ' . (string) ($app['status'] ?? 'unknown'),
    ];
    $html = '<div class="ow-tags">';
    foreach ($items as $item) {
        $html .= '<span>' . $h($item) . '</span>';
    }
    return $html . '</div>';
};

$buildMermaidDiagram = static function (?array $app) use ($link, $mermaidLabel): string {
    $appLabel = $app === null
        ? 'No application selected'
        : $mermaidLabel((string) ($app['name'] ?? $app['id'] ?? 'Current application'));
    $appKind = $app === null ? 'choose in Registry' : $mermaidLabel((string) ($app['kind'] ?? 'unknown'));

    return implode("\n", [
        'flowchart LR',
        '    registry["Registry<br/>choose or create"]:::primary',
        '    current["' . $appLabel . '<br/>' . $appKind . '"]:::current',
        '    structure["Structure"]:::work',
        '    data["Data"]:::work',
        '    workflows["Workflows"]:::work',
        '    security["Security"]:::work',
        '    build["Build & Validate"]:::work',
        '    registry --> current',
        '    current --> structure',
        '    current --> data',
        '    current --> workflows',
        '    current --> security',
        '    current --> build',
        '    registry --> build',
        '    click registry "' . $link('/applications') . '" "Open Registry"',
        '    click structure "' . $link('/structure') . '" "Open Structure"',
        '    click data "' . $link('/data') . '" "Open Data"',
        '    click workflows "' . $link('/workflows') . '" "Open Workflows"',
        '    click security "' . $link('/security') . '" "Open Security"',
        '    click build "' . $link('/build') . '" "Open Build"',
        '    classDef primary fill:#123456,stroke:#6ce3ff,color:#f6f8ff,stroke-width:2px',
        '    classDef current fill:#164e63,stroke:#4ade80,color:#f6f8ff,stroke-width:3px',
        '    classDef work fill:#101c2f,stroke:#94aad8,color:#f6f8ff,stroke-width:1px',
    ]);
};

$renderMermaidPanel = static function (string $diagram) use ($h): string {
    return '<section class="ow-card ow-mermaid-panel" data-context="OWASYS_MERMAID_NAVIGATION">'
        . '<h2>Visual navigation</h2>'
        . '<p class="ow-muted">Clickable Mermaid map of the current OWASYS application context.</p>'
        . '<pre class="mermaid">' . $h($diagram) . '</pre>'
        . '<p class="ow-muted ow-mermaid-fallback">If Mermaid is unavailable, use the sidebar and Registry buttons.</p>'
        . '</section>';
};

$body = '<div class="ow-shell">';
$body .= '<aside class="ow-sidebar">';
$body .= '<div class="ow-brand"><strong>OWASYS</strong><span>OPUS Web Application System</span></div>';
$body .= '<div class="ow-auth-status">';
if ($isAuthenticated) {
    $body .= '<span class="ow-auth-dot" aria-hidden="true"></span><strong>' . $h((string) ($user['label'] ?? 'User')) . '</strong>';
    $body .= '<small>profile: ' . $h((string) ($user['profile'] ?? 'unknown')) . '</small>';
    if ($mustChangePassword) {
        $body .= '<small class="ow-auth-warning">password change required</small>';
    }
    $body .= '<a href="' . $h($link('/account/password')) . '">Password</a>';
    $body .= '<a href="' . $h($link('/logout')) . '">Logout</a>';
} else {
    $body .= '<span class="ow-auth-dot is-off" aria-hidden="true"></span><strong>Not signed in</strong>';
    $body .= '<small>password session inactive</small>';
    $body .= '<a href="' . $h($link('/login')) . '">Login</a>';
}
$body .= '</div>';
if ($isAuthenticated) {
    $body .= '<div class="ow-current-app">';
    $body .= '<small>Current application</small>';
    if ($currentApp !== null) {
        $body .= '<strong>' . $h((string) ($currentApp['name'] ?? $currentApp['id'] ?? 'unknown')) . '</strong>';
        $body .= '<span>' . $h((string) ($currentApp['kind'] ?? 'unknown')) . ' · ' . $h((string) ($currentApp['root_path'] ?? 'unknown')) . '</span>';
    } else {
        $body .= '<strong>None selected</strong>';
        $body .= '<span>Choose an app in Registry.</span>';
    }
    $body .= '<a href="' . $h($link('/applications')) . '">Change application</a>';
    $body .= '</div>';
}
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

if ($isAuthenticated) {
    $body .= '<section class="ow-current-app-hero" data-context="OWASYS_CURRENT_APP_CONTEXT">';
    $body .= '<small>YOU ARE WORKING ON</small>';
    if ($currentApp !== null) {
        $body .= '<strong>' . $h((string) ($currentApp['name'] ?? $currentApp['id'] ?? 'unknown')) . '</strong>';
        $body .= $renderAppTags($currentApp);
    } else {
        $body .= '<strong>No application selected</strong>';
        $body .= '<p>Choose an existing OPUS application in the Registry, or create a new one.</p>';
    }
    $body .= '<p><a class="ow-button ow-button-secondary" href="' . $h($link('/applications')) . '">Change application</a></p>';
    $body .= '</section>';
}

if ($isAuthenticated && !in_array($controller, ['login', 'account'], true)) {
    $body .= $renderMermaidPanel($buildMermaidDiagram($currentApp));
}

if ($currentApp !== null && !in_array($controller, ['login', 'account', 'registry'], true)) {
    $body .= '<section class="ow-card ow-context-panel">';
    $body .= '<h2>Application context</h2>';
    $body .= '<p>All configuration changes in this section target the selected OPUS application.</p>';
    $body .= $renderAppTags($currentApp);
    $body .= '<p><a class="ow-button ow-button-secondary" href="' . $h($link('/applications')) . '">Change application</a></p>';
    $body .= '</section>';
}

if ($controller === 'login') {
    $body .= '<section class="ow-card ow-auth-panel">';
    if ($isAuthenticated) {
        $body .= '<h2>Session active</h2>';
        $body .= '<p>You are signed in for this local OWASYS session.</p>';
        $body .= '<div class="ow-tags"><span>profile: ' . $h((string) ($user['profile'] ?? 'unknown')) . '</span><span>mode: ' . $h((string) ($user['mode'] ?? 'unknown')) . '</span></div>';
        $body .= '<p><a class="ow-button" href="' . $h($link('/logout')) . '">Logout</a><a class="ow-button ow-button-secondary" href="' . $h($link('/applications')) . '">Registry</a></p>';
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

if ($controller === 'account') {
    $body .= '<section class="ow-card ow-auth-panel">';
    $body .= '<h2>Change password</h2>';
    $body .= '<p>Update the runtime password for the current OWASYS user. This is required for bootstrap users before accessing the dashboard.</p>';
    if ($mustChangePassword) {
        $body .= '<p class="ow-login-warning">Password change required before continuing.</p>';
    }
    if ($passwordChangeError !== null) {
        $body .= '<p class="ow-login-error">' . $h($passwordChangeError) . '</p>';
    }
    $body .= '<form method="post" class="ow-password-form">';
    $body .= '<input type="hidden" name="owasys_action" value="change-password">';
    $body .= '<label>Current password<input name="owasys_current_password" type="password" autocomplete="current-password" required></label>';
    $body .= '<label>New password<input name="owasys_new_password" type="password" autocomplete="new-password" minlength="10" required></label>';
    $body .= '<label>Confirm new password<input name="owasys_confirm_password" type="password" autocomplete="new-password" minlength="10" required></label>';
    $body .= '<button class="ow-button" type="submit">Change password</button>';
    $body .= '</form>';
    $body .= '</section>';
}

if ($controller === 'registry') {
    $entries = (array) ($page['registry_entries'] ?? []);
    $body .= '<section class="ow-card ow-context-panel">';
    $body .= '<h2>Application context</h2>';
    if ($registryActionError !== null) {
        $body .= '<p class="ow-login-error">' . $h($registryActionError) . '</p>';
    }
    $body .= '<p>Select an existing OPUS application to edit it, or start the creation flow for a new one.</p>';
    $body .= $renderAppTags($currentApp);
    $body .= '<form method="post" class="ow-inline-form"><input type="hidden" name="owasys_action" value="create-new-app"><button class="ow-button" type="submit">Create new application</button></form>';
    if ($currentApp !== null) {
        $body .= '<form method="post" class="ow-inline-form"><input type="hidden" name="owasys_action" value="clear-app-context"><button class="ow-button ow-button-secondary" type="submit">Clear current context</button></form>';
    }
    $body .= '</section>';

    $body .= '<section class="ow-grid ow-registry-grid">';
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $entryId = (string) ($entry['id'] ?? '');
        $isCurrent = $currentApp !== null && (string) ($currentApp['id'] ?? '') === $entryId;
        $body .= '<article class="ow-card ow-registry-card">';
        $body .= '<h2>' . $h((string) ($entry['name'] ?? $entryId)) . '</h2>';
        $body .= '<p>Registered OPUS target: ' . $h((string) ($entry['root_path'] ?? 'unknown')) . '</p>';
        $body .= $renderAppTags($entry);
        $body .= '<form method="post" class="ow-inline-form">';
        $body .= '<input type="hidden" name="owasys_action" value="select-app">';
        $body .= '<input type="hidden" name="owasys_app_id" value="' . $h($entryId) . '">';
        $body .= '<button class="ow-button" type="submit">' . ($isCurrent ? 'Current app' : 'Work on this app') . '</button>';
        $body .= '</form>';
        $body .= '</article>';
    }
    $body .= '</section>';
}

$cards = $controller === 'registry' ? [] : (array) ($page['cards'] ?? []);
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
} elseif ($controller !== 'registry') {
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
    . '<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>'
    . '<script src="' . $h($asset('/asset/js/owasys.js')) . '"></script>'
    . '<script src="' . $h($asset('/asset/themes/owasys/js/theme.js')) . '"></script>'
    . '</body></html>';
