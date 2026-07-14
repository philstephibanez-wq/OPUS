<?php
declare(strict_types=1);

use Opus\Fsm\FsmSiteLoader;
use Opus\Owasys\ApplicationInspector;
use Opus\Owasys\RegistryRepository;
use Opus\Owasys\StructureDraftRepository;

/**
 * OWASYS public entry.
 *
 * Standard OPUS site entry for the OWASYS application.
 * It renders data-only state view-models and drives navigation from OWASYS_NAVIGATION_FSM_V1.
 */

$siteRoot = dirname(__DIR__);
$opusRoot = dirname(dirname($siteRoot));
$autoload = $opusRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo 'OWASYS_COMPOSER_AUTOLOAD_MISSING';
    exit;
}
require_once $autoload;

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
if (($siteConfig['states_root'] ?? null) !== 'application/states' || ($siteConfig['dispatch_model'] ?? null) !== 'state-first') {
    http_response_code(500);
    echo 'OWASYS_STATE_ROOT_INVALID';
    exit;
}

$navigationConfig = is_array($siteConfig['navigation'] ?? null) ? $siteConfig['navigation'] : [];
$fsmRelative = trim(str_replace('\\', '/', (string) ($navigationConfig['fsm'] ?? 'config/owasys-navigation.fsm.json')), '/');
if ($fsmRelative === '' || str_contains($fsmRelative, '..')) {
    http_response_code(500);
    echo 'OWASYS_NAVIGATION_FSM_PATH_INVALID';
    exit;
}
$fsmFile = $siteRoot . '/' . $fsmRelative;
$fsmConfig = is_file($fsmFile) ? json_decode((string) file_get_contents($fsmFile), true) : null;
if (!is_array($fsmConfig) || ($fsmConfig['contract'] ?? null) !== 'OWASYS_NAVIGATION_FSM_V1') {
    http_response_code(500);
    echo 'OWASYS_NAVIGATION_FSM_INVALID';
    exit;
}

try {
    $owasysFsmProcessor = FsmSiteLoader::processorForSite($opusRoot, 'owasys');
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'OWASYS_RUNTIME_FSM_PROCESSOR_INVALID';
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
$locales = array_values(array_filter((array) ($siteConfig['locales'] ?? ['fr']), 'is_string'));
$defaultLocale = in_array((string) ($siteConfig['default_locale'] ?? 'fr'), $locales, true) ? (string) ($siteConfig['default_locale'] ?? 'fr') : 'fr';
$requestedLocale = strtolower((string) ($_GET['lang'] ?? $_SESSION['owasys_locale'] ?? $defaultLocale));
$locale = in_array($requestedLocale, $locales, true) ? $requestedLocale : $defaultLocale;
$_SESSION['owasys_locale'] = $locale;
$loadMessages = static function (string $locale) use ($siteRoot): array {
    $file = $siteRoot . '/application/default/local/' . $locale . '.php';
    if (!is_file($file)) {
        return [];
    }
    $messages = require $file;
    return is_array($messages) ? $messages : [];
};
$messages = array_replace($loadMessages('en'), $loadMessages($defaultLocale), $loadMessages($locale));
$t = static function (string $key) use (&$messages): string {
    $value = $messages[$key] ?? null;
    return is_string($value) && $value !== '' ? $value : $key;
};
$mermaidLabel = static function (string $value): string {
    $clean = preg_replace('/[^A-Za-z0-9 _.:\/\-]/', '', $value);
    $clean = trim(is_string($clean) ? $clean : '');
    return $clean === '' ? 'unknown' : $clean;
};

$registryConfig = is_array($siteConfig['registry'] ?? null) ? $siteConfig['registry'] : [];
$registrySeedRelative = trim(str_replace('\\', '/', (string) ($registryConfig['seed'] ?? 'config/registry.seed.json')), '/');
$registryDatabaseRelative = isset($owasysRegistryDatabaseRelative) && is_string($owasysRegistryDatabaseRelative)
    ? $owasysRegistryDatabaseRelative
    : trim(str_replace('\\', '/', (string) ($registryConfig['runtime_database'] ?? 'var/registry/owasys.sqlite')), '/');
if ($registrySeedRelative === '' || str_contains($registrySeedRelative, '..') || $registryDatabaseRelative === '' || str_contains($registryDatabaseRelative, '..')) {
    http_response_code(500);
    echo 'OWASYS_RUNTIME_REGISTRY_PATH_INVALID';
    exit;
}
$registrySeedFile = $siteRoot . '/' . $registrySeedRelative;
try {
    $owasysRegistryRepository = RegistryRepository::forOwasysSite($siteRoot, $opusRoot, $registryDatabaseRelative);
    $owasysRegistrySync = $owasysRegistryRepository->synchronize($registrySeedFile);
    $owasysApplicationInspector = ApplicationInspector::forOpusRoot($opusRoot);
    $owasysStructureDraftRepository = StructureDraftRepository::forRegistry($owasysRegistryRepository);
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'OWASYS_RUNTIME_REGISTRY_INVALID';
    exit;
}

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

$statesByRoute = [];
$statesById = [];
foreach ((array) ($fsmConfig['states'] ?? []) as $stateConfig) {
    if (!is_array($stateConfig)) {
        continue;
    }
    $stateId = (string) ($stateConfig['id'] ?? '');
    if ($stateId === '') {
        continue;
    }
    $statesById[$stateId] = $stateConfig;
    $stateRoute = (string) ($stateConfig['route'] ?? '');
    if ($stateRoute !== '') {
        $statesByRoute[$stateRoute] = $stateConfig;
    }
}
$routeForTransition = static function (array $transition) use ($statesById): string {
    $targetState = (string) ($transition['to_state'] ?? '');
    $target = is_array($statesById[$targetState] ?? null) ? $statesById[$targetState] : [];
    $route = (string) ($target['route'] ?? '');
    if ($route === '') {
        http_response_code(500);
        echo 'OWASYS_RUNTIME_FSM_TRANSITION_ROUTE_MISSING';
        exit;
    }
    return $route;
};
$runtimeCurrentState = static function () use ($statesById): string {
    $candidate = (string) ($_SESSION['owasys_current_state'] ?? 'home');
    return isset($statesById[$candidate]) ? $candidate : 'home';
};
$redirectAfterTransition = static function (array $transition) use ($routeForTransition, $redirect): void {
    $redirect($routeForTransition($transition));
};

$loginErrorKey = null;
$passwordChangeErrorKey = null;
$registryActionErrorKey = null;
$structureActionResult = null;
$structureDraftResult = null;
$structureActionErrorKey = null;

if ($path === '/logout') {
    $transition = $owasysFsmProcessor->transition($runtimeCurrentState(), 'logout');
    $owasysRegistryRepository->logout(is_array($_SESSION['owasys_user'] ?? null) ? (string) ($_SESSION['owasys_user']['id'] ?? '') : null);
    unset($_SESSION['owasys_user'], $_SESSION['owasys_current_app'], $_SESSION['owasys_current_state']);
    session_regenerate_id(true);
    $redirectAfterTransition($transition);
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
        $loginErrorKey = 'auth.error.required_credentials';
    } else {
        $users = $loadRuntimeUsers($authStoreFile);
        $candidate = is_array($users[$username] ?? null) ? $users[$username] : null;
        $passwordHash = is_array($candidate) ? (string) ($candidate['password_hash'] ?? '') : '';
        if ($candidate === null || $passwordHash === '' || !password_verify($password, $passwordHash)) {
            $loginErrorKey = 'auth.error.invalid_credentials';
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
            $transition = ($_SESSION['owasys_user']['must_change_password'] ?? false) === true
                ? $owasysFsmProcessor->transition('login', 'password_change_required', ['must_change_password' => true])
                : $owasysFsmProcessor->transition('login', 'login_success');
            $redirectAfterTransition($transition);
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
        $passwordChangeErrorKey = 'auth.error.runtime_user_missing';
    } elseif ($currentPassword === '' || !password_verify($currentPassword, $passwordHash)) {
        $passwordChangeErrorKey = 'auth.error.current_password_invalid';
    } elseif (strlen($newPassword) < 10) {
        $passwordChangeErrorKey = 'auth.error.new_password_too_short';
    } elseif ($newPassword !== $confirmPassword) {
        $passwordChangeErrorKey = 'auth.error.confirmation_mismatch';
    } elseif (password_verify($newPassword, $passwordHash)) {
        $passwordChangeErrorKey = 'auth.error.password_unchanged';
    } else {
        $candidate['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $candidate['must_change_password'] = false;
        $candidate['password_changed_at'] = gmdate('c');
        $candidate['updated_at'] = gmdate('c');
        $store['users'][$userId] = $candidate;
        $writeRuntimeStore($authStoreFile, $store);
        $_SESSION['owasys_user']['must_change_password'] = false;
        $_SESSION['owasys_user']['password_changed_at'] = $candidate['password_changed_at'];
        $transition = $owasysFsmProcessor->transition('account', 'password_changed');
        $redirectAfterTransition($transition);
    }
}

$user = is_array($_SESSION['owasys_user'] ?? null) ? $_SESSION['owasys_user'] : null;
$isAuthenticated = is_array($user);
$mustChangePassword = $isAuthenticated && (($user['must_change_password'] ?? false) === true);
if ($mustChangePassword && !in_array($path, ['/account/password', '/logout'], true)) {
    $redirect('/account/password');
}

$route = null;
foreach ($routesConfig['routes'] as $candidateRoute) {
    if (is_array($candidateRoute) && ($candidateRoute['path'] ?? null) === $path) {
        $route = $candidateRoute;
        break;
    }
}
if (!is_array($route)) {
    http_response_code(404);
    echo 'OWASYS_ROUTE_NOT_FOUND: ' . $h($path);
    exit;
}
$state = (string) ($route['state'] ?? ($route['controller'] ?? ''));
$controller = (string) ($route['controller'] ?? $state);
if (!preg_match('/^[a-z0-9_-]+$/', $state)) {
    http_response_code(500);
    echo 'OWASYS_STATE_INVALID';
    exit;
}
if (!preg_match('/^[a-z0-9_-]+$/', $controller)) {
    http_response_code(500);
    echo 'OWASYS_CONTROLLER_INVALID';
    exit;
}
$viewFile = $siteRoot . '/application/states/' . $state . '/views/index.php';
if (!is_file($viewFile)) {
    http_response_code(500);
    echo 'OWASYS_VIEW_MISSING: ' . $h($state);
    exit;
}
$page = require $viewFile;
if (!is_array($page)) {
    http_response_code(500);
    echo 'OWASYS_VIEW_MODEL_INVALID';
    exit;
}

$currentApp = is_array($_SESSION['owasys_current_app'] ?? null) ? $_SESSION['owasys_current_app'] : ($isAuthenticated ? $owasysRegistryRepository->currentApplication() : null);
if ($isAuthenticated && is_array($currentApp)) {
    $_SESSION['owasys_current_app'] = $currentApp;
}
$currentState = $statesByRoute[$path] ?? null;
$findRegistryEntry = static function (array $entries, string $id): ?array {
    foreach ($entries as $entry) {
        if (is_array($entry) && (string) ($entry['id'] ?? '') === $id) {
            return $entry;
        }
    }
    return null;
};

if ($state === 'registry' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['owasys_action'] ?? '');
    if ($action === 'select-app') {
        $appId = trim((string) ($_POST['owasys_app_id'] ?? ''));
        $entry = $findRegistryEntry((array) ($page['registry_entries'] ?? []), $appId);
        if ($entry === null) {
            $registryActionErrorKey = 'registry.error.selected_application_not_registered';
        } else {
            $transition = $owasysFsmProcessor->transition('registry', 'select_app', [
                'app_exists' => true,
                'registry_entry' => $entry,
                'selected_app' => $appId,
            ]);
            $owasysRegistryRepository->setCurrentApplication($entry, is_array($user) ? (string) ($user['id'] ?? '') : null);
            $_SESSION['owasys_current_app'] = $entry;
            $redirectAfterTransition($transition);
        }
    } elseif ($action === 'clear-app-context') {
        $transition = $owasysFsmProcessor->transition('registry', 'clear_app_context');
        $owasysRegistryRepository->clearCurrentApplication(is_array($user) ? (string) ($user['id'] ?? '') : null);
        unset($_SESSION['owasys_current_app']);
        $redirectAfterTransition($transition);
    } elseif ($action === 'create-new-app') {
        $transition = $owasysFsmProcessor->transition('registry', 'create_new_app');
        $owasysRegistryRepository->startCreationFlow(is_array($user) ? (string) ($user['id'] ?? '') : null);
        unset($_SESSION['owasys_current_app']);
        $redirectAfterTransition($transition);
    } else {
        http_response_code(400);
        echo 'OWASYS_REGISTRY_ACTION_INVALID';
        exit;
    }
}

$currentApp = is_array($_SESSION['owasys_current_app'] ?? null) ? $_SESSION['owasys_current_app'] : ($isAuthenticated ? $owasysRegistryRepository->currentApplication() : null);
$requiresCurrentApp = is_array($currentState) && (($currentState['requires_current_app'] ?? false) === true);
if ($isAuthenticated && $requiresCurrentApp && $currentApp === null) {
    $transition = $owasysFsmProcessor->transition($state, 'change_app');
    $redirectAfterTransition($transition);
}

$currentApplicationInspection = null;
$structureDrafts = [];
if ($isAuthenticated && $state === 'structure' && is_array($currentApp)) {
    try {
        $currentApplicationInspection = $owasysApplicationInspector->inspectEntry($currentApp);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $action = (string) ($_POST['owasys_action'] ?? '');
            if ($action === 'validate-current-application') {
                $structureActionResult = $owasysRegistryRepository->recordStructureValidation($currentApp, $currentApplicationInspection, is_array($user) ? (string) ($user['id'] ?? '') : null);
                $currentApp = $owasysRegistryRepository->currentApplication() ?? $currentApp;
                $_SESSION['owasys_current_app'] = $currentApp;
            } elseif ($action === 'prepare-add-state-draft') {
                $structureDraftResult = $owasysStructureDraftRepository->prepareAddStateDraft($currentApp, $currentApplicationInspection, [
                    'state_id' => (string) ($_POST['owasys_state_id'] ?? ''),
                    'route_path' => (string) ($_POST['owasys_route_path'] ?? ''),
                    'title_key' => (string) ($_POST['owasys_title_key'] ?? ''),
                    'event_name' => (string) ($_POST['owasys_event_name'] ?? ''),
                ], is_array($user) ? (string) ($user['id'] ?? '') : null);
            } else {
                http_response_code(400);
                echo 'OWASYS_STRUCTURE_ACTION_INVALID';
                exit;
            }
        }
        $structureDrafts = $owasysStructureDraftRepository->recentDrafts((string) ($currentApp['id'] ?? ''), 8);
    } catch (Throwable $exception) {
        $structureActionErrorKey = 'draft.error.invalid_request';
    }
}

$menu = [];
foreach ($routesConfig['routes'] as $candidateRoute) {
    if (is_array($candidateRoute) && ($candidateRoute['show_in_menu'] ?? false) === true) {
        $menu[] = $candidateRoute;
    }
}
usort($menu, static fn (array $a, array $b): int => ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0)));
$asset = static fn (string $assetPath): string => $mount . '/' . ltrim($assetPath, '/');
$pageTitle = $t('state.' . $state . '.title');
$pageSummary = $t('state.default.summary');

$renderList = static function (array $items) use ($h, $t): string {
    if ($items === []) {
        return '';
    }
    $html = '<ul>';
    foreach ($items as $item) {
        $label = (string) $item;
        if (str_contains($label, '.')) {
            $label = $t($label);
        }
        $html .= '<li>' . $h($label) . '</li>';
    }
    return $html . '</ul>';
};
$renderAppTags = static function (?array $app) use ($h, $t): string {
    if ($app === null) {
        return '<div class="ow-tags"><span>' . $h($t('registry.no_current_application')) . '</span></div>';
    }
    $items = [
        $t('common.id') . ': ' . (string) ($app['id'] ?? $t('common.unknown')),
        $t('common.type') . ': ' . (string) ($app['kind'] ?? $t('common.unknown')),
        $t('common.root') . ': ' . (string) ($app['root_path'] ?? $t('common.unknown')),
        $t('common.status') . ': ' . (string) ($app['status'] ?? $t('common.unknown')),
        $t('registry.source') . ': ' . (string) ($app['source'] ?? 'sqlite'),
    ];
    $html = '<div class="ow-tags">';
    foreach ($items as $item) {
        $html .= '<span>' . $h($item) . '</span>';
    }
    return $html . '</div>';
};
$renderAppTree = static function (array $entries, ?array $currentApp) use ($h, $t): string {
    $groups = ['fullstack' => [], 'frontend' => [], 'backend' => [], 'package' => []];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $kind = (string) ($entry['kind'] ?? 'fullstack');
        $groups[array_key_exists($kind, $groups) ? $kind : 'fullstack'][] = $entry;
    }
    $html = '<section class="ow-card ow-app-tree" data-context="OWASYS_REGISTRY_APP_TREE">';
    $html .= '<h2>' . $h($t('registry.application_tree')) . '</h2>';
    $html .= '<p class="ow-muted">' . $h($t('registry.tree_help')) . '</p>';
    $html .= '<div class="ow-tree-root"><strong>' . $h($t('brand.name')) . '</strong>';
    $html .= '<form method="post" class="ow-inline-form"><input type="hidden" name="owasys_action" value="create-new-app"><button class="ow-button" type="submit">' . $h($t('registry.create_new_application')) . '</button></form>';
    $html .= '</div><div class="ow-tree-branches">';
    foreach ($groups as $kind => $apps) {
        $html .= '<div class="ow-tree-kind"><h3>' . $h($kind) . '</h3>';
        if ($apps === []) {
            $html .= '<p class="ow-muted">' . $h($t('registry.no_registered_application')) . '</p>';
        }
        foreach ($apps as $entry) {
            $entryId = (string) ($entry['id'] ?? '');
            $isCurrent = $currentApp !== null && (string) ($currentApp['id'] ?? '') === $entryId;
            $html .= '<form method="post" class="ow-tree-app' . ($isCurrent ? ' is-current' : '') . '">';
            $html .= '<input type="hidden" name="owasys_action" value="select-app">';
            $html .= '<input type="hidden" name="owasys_app_id" value="' . $h($entryId) . '">';
            $html .= '<button type="submit"><strong>' . $h((string) ($entry['name'] ?? $entryId)) . '</strong>';
            $html .= '<span>' . $h((string) ($entry['root_path'] ?? $t('common.unknown'))) . '</span>';
            $html .= '<em>' . $h($isCurrent ? $t('registry.current_context') : $t('registry.click_to_work_on_this_app')) . '</em>';
            $html .= '</button></form>';
        }
        $html .= '</div>';
    }
    return $html . '</div></section>';
};
$renderEvents = static function (array $events) use ($h, $t): string {
    $html = '<section class="ow-card"><h2>' . $h($t('registry.events.title')) . '</h2>';
    if ($events === []) {
        return $html . '<p class="ow-muted">' . $h($t('registry.events.empty')) . '</p></section>';
    }
    $html .= '<div class="ow-tags">';
    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }
        $eventType = (string) ($event['event_type'] ?? '');
        $eventLabelKey = 'registry.events.' . $eventType;
        $label = $t($eventLabelKey) !== $eventLabelKey ? $t($eventLabelKey) : $eventType;
        $html .= '<span>' . $h($t('registry.events.type') . ': ' . $label . ' · ' . $t('registry.events.application') . ': ' . (string) ($event['application_id'] ?? '') . ' · ' . $t('registry.events.created_at') . ': ' . (string) ($event['created_at'] ?? '')) . '</span>';
    }
    return $html . '</div></section>';
};
$renderDrafts = static function (array $drafts, ?array $draftResult = null, ?string $errorKey = null) use ($h, $t): string {
    $html = '<section class="ow-card" data-context="OWASYS_STRUCTURE_DRAFTS_RECENT"><h2>' . $h($t('draft.recent_title')) . '</h2>';
    if ($errorKey !== null) {
        $html .= '<p class="ow-login-error">' . $h($t($errorKey)) . '</p>';
    }
    if ($draftResult !== null) {
        $html .= '<div class="ow-tags" data-context="OWASYS_STRUCTURE_DRAFT_RESULT"><span>' . $h($t('draft.result')) . ': ' . $h((string) ($draftResult['state_id'] ?? '')) . '</span><span>' . $h($t('draft.route')) . ': ' . $h((string) ($draftResult['route_path'] ?? '')) . '</span><span>' . $h($t('draft.disk_mutation_false')) . '</span></div>';
    }
    if ($drafts === []) {
        return $html . '<p class="ow-muted">' . $h($t('draft.empty')) . '</p></section>';
    }
    $html .= '<div class="ow-tags">';
    foreach ($drafts as $draft) {
        if (!is_array($draft)) {
            continue;
        }
        $html .= '<span>' . $h($t('draft.state') . ': ' . (string) ($draft['state_id'] ?? '') . ' · ' . $t('draft.route') . ': ' . (string) ($draft['route_path'] ?? '') . ' · ' . $t('draft.event') . ': ' . (string) ($draft['event_name'] ?? '')) . '</span>';
    }
    return $html . '</div></section>';
};
$renderInspection = static function (array $inspection, ?array $actionResult = null, ?array $draftResult = null, array $drafts = [], ?string $errorKey = null) use ($h, $t, $renderDrafts): string {
    $html = '<section class="ow-card" data-context="OWASYS_APPLICATION_INSPECTION">';
    $html .= '<h2>' . $h($t('inspection.title')) . '</h2>';
    $html .= '<p class="ow-muted">' . $h($t('inspection.description')) . '</p>';
    if ($actionResult !== null) {
        $html .= '<div class="ow-tags" data-context="OWASYS_STRUCTURE_ACTION_RESULT"><span>' . $h($t('inspection.validation_result')) . ': ' . $h($t('inspection.validation_valid')) . '</span><span>' . $h($t('inspection.validated_at')) . ': ' . $h((string) ($actionResult['validated_at'] ?? '')) . '</span><span>' . $h($t('inspection.validated_by')) . ': ' . $h((string) ($actionResult['validated_by'] ?? '')) . '</span></div>';
    }
    $html .= '<form method="post" class="ow-inline-form"><input type="hidden" name="owasys_action" value="validate-current-application"><button class="ow-button" type="submit">' . $h($t('inspection.action.validate_now')) . '</button></form>';
    $html .= '<section class="ow-card" data-context="OWASYS_STRUCTURE_ADD_STATE_DRAFT_FORM"><h3>' . $h($t('draft.add_state_title')) . '</h3><p class="ow-muted">' . $h($t('draft.description')) . '</p>';
    $html .= '<form method="post" class="ow-password-form"><input type="hidden" name="owasys_action" value="prepare-add-state-draft"><label>' . $h($t('draft.state_id')) . '<input name="owasys_state_id" value="faq" required></label><label>' . $h($t('draft.route_path')) . '<input name="owasys_route_path" value="/faq" required></label><label>' . $h($t('draft.title_key')) . '<input name="owasys_title_key" value="state.faq.title" required></label><label>' . $h($t('draft.event_name')) . '<input name="owasys_event_name" value="open_faq" required></label><button class="ow-button" type="submit">' . $h($t('draft.prepare')) . '</button></form></section>';
    $html .= '<div class="ow-tags">';
    foreach ([
        $t('common.id') . ': ' . (string) ($inspection['site_id'] ?? ''),
        $t('common.root') . ': ' . (string) ($inspection['root_path'] ?? ''),
        $t('common.contract') . ': ' . (string) ($inspection['inspection_contract'] ?? ''),
        $t('common.fsm') . ': ' . (string) ($inspection['fsm_relative_path'] ?? ''),
        $t('common.states') . ': ' . (string) ($inspection['state_count'] ?? '0'),
        $t('common.routes') . ': ' . (string) ($inspection['route_count'] ?? '0'),
        $t('inspection.transitions') . ': ' . (string) ($inspection['transition_count'] ?? '0'),
    ] as $item) {
        $html .= '<span>' . $h($item) . '</span>';
    }
    $html .= '</div>';
    $html .= '<h3>' . $h($t('inspection.states')) . '</h3><div class="ow-tags">';
    foreach ((array) ($inspection['states'] ?? []) as $stateRow) {
        if (is_array($stateRow)) {
            $html .= '<span>' . $h((string) ($stateRow['id'] ?? '') . ' · ' . (string) ($stateRow['directory'] ?? '')) . '</span>';
        }
    }
    $html .= '</div><h3>' . $h($t('inspection.routes')) . '</h3><div class="ow-tags">';
    foreach ((array) ($inspection['routes'] ?? []) as $routeRow) {
        if (is_array($routeRow)) {
            $html .= '<span>' . $h((string) ($routeRow['path'] ?? '') . ' → ' . (string) ($routeRow['state'] ?? '') . ' · ' . (string) ($routeRow['view'] ?? '')) . '</span>';
        }
    }
    return $html . '</div></section>' . $renderDrafts($drafts, $draftResult, $errorKey);
};
$buildMermaidDiagram = static function (array $fsm, ?array $app, ?string $currentStateId) use ($link, $mermaidLabel, $t): string {
    $states = [];
    foreach ((array) ($fsm['states'] ?? []) as $stateConfig) {
        if (is_array($stateConfig) && (string) ($stateConfig['id'] ?? '') !== '') {
            $states[(string) $stateConfig['id']] = $stateConfig;
        }
    }
    $lines = ['flowchart LR'];
    foreach ($states as $id => $stateConfig) {
        if ($id === 'login' || $id === 'account') {
            continue;
        }
        $class = $id === $currentStateId ? 'active' : (($stateConfig['requires_current_app'] ?? false) === true ? 'work' : 'primary');
        $label = $mermaidLabel($t('state.' . $id . '.title'));
        if ($id === 'structure' && $app !== null) {
            $label .= '<br/>' . $mermaidLabel((string) ($app['name'] ?? $app['id'] ?? ''));
        }
        $lines[] = '    ' . $id . '["' . $label . '"]:::' . $class;
    }
    foreach ((array) ($fsm['transitions'] ?? []) as $transition) {
        if (!is_array($transition) || ($transition['visual'] ?? false) !== true) {
            continue;
        }
        $from = (string) ($transition['from'] ?? '');
        $to = (string) ($transition['to'] ?? '');
        if ($from === '' || $to === '' || !isset($states[$from], $states[$to]) || in_array($from, ['login', 'account'], true) || in_array($to, ['login', 'account'], true)) {
            continue;
        }
        $lines[] = '    ' . $from . ' -->|' . $mermaidLabel((string) ($transition['event'] ?? 'event')) . '| ' . $to;
    }
    foreach ($states as $id => $stateConfig) {
        if (in_array($id, ['login', 'account'], true)) {
            continue;
        }
        $route = (string) ($stateConfig['route'] ?? '');
        if ($route !== '') {
            $lines[] = '    click ' . $id . ' "' . $link($route) . '" "' . $mermaidLabel($t('common.open') . ' ' . $t('state.' . $id . '.title')) . '"';
        }
    }
    $lines[] = '    classDef primary fill:#123456,stroke:#6ce3ff,color:#f6f8ff,stroke-width:2px';
    $lines[] = '    classDef active fill:#164e63,stroke:#4ade80,color:#f6f8ff,stroke-width:4px';
    $lines[] = '    classDef work fill:#101c2f,stroke:#94aad8,color:#f6f8ff,stroke-width:1px';
    return implode("\n", $lines);
};
$renderMermaidPanel = static function (string $diagram) use ($h, $t): string {
    return '<section class="ow-card ow-mermaid-panel" data-context="OWASYS_MERMAID_NAVIGATION"><h2>' . $h($t('mermaid.title')) . '</h2>'
        . '<p class="ow-muted">' . $h($t('mermaid.description')) . '</p>'
        . '<pre class="mermaid">' . $h($diagram) . '</pre><p class="ow-muted ow-mermaid-fallback">' . $h($t('mermaid.fallback')) . '</p></section>';
};

$body = '<div class="ow-shell"><aside class="ow-sidebar">';
$body .= '<div class="ow-brand"><strong>' . $h($t('brand.name')) . '</strong><span>' . $h($t('brand.subtitle')) . '</span></div><div class="ow-auth-status">';
if ($isAuthenticated) {
    $body .= '<span class="ow-auth-dot" aria-hidden="true"></span><strong>' . $h((string) ($user['label'] ?? $t('common.unknown'))) . '</strong><small>' . $h($t('common.profile')) . ': ' . $h((string) ($user['profile'] ?? $t('common.unknown'))) . '</small>';
    if ($mustChangePassword) {
        $body .= '<small class="ow-auth-warning">' . $h($t('auth.password_change_required')) . '</small>';
    }
    $body .= '<a href="' . $h($link('/account/password')) . '">' . $h($t('auth.password')) . '</a><a href="' . $h($link('/logout')) . '">' . $h($t('auth.logout')) . '</a>';
} else {
    $body .= '<span class="ow-auth-dot is-off" aria-hidden="true"></span><strong>' . $h($t('auth.not_signed_in')) . '</strong><small>' . $h($t('auth.session_inactive')) . '</small><a href="' . $h($link('/login')) . '">' . $h($t('auth.login')) . '</a>';
}
$body .= '</div>';
if ($isAuthenticated) {
    $body .= '<div class="ow-current-app"><small>' . $h($t('registry.current_application')) . '</small>';
    if ($currentApp !== null) {
        $body .= '<strong>' . $h((string) ($currentApp['name'] ?? $currentApp['id'] ?? $t('common.unknown'))) . '</strong><span>' . $h((string) ($currentApp['kind'] ?? $t('common.unknown'))) . ' · ' . $h((string) ($currentApp['root_path'] ?? $t('common.unknown'))) . '</span>';
    } else {
        $body .= '<strong>' . $h($t('registry.none_selected')) . '</strong><span>' . $h($t('registry.choose_in_registry')) . '</span>';
    }
    $body .= '<a href="' . $h($link('/applications')) . '">' . $h($t('registry.change_application')) . '</a></div>';
}
$body .= '<nav class="ow-nav">';
foreach ($menu as $item) {
    $labelKey = (string) ($item['label'] ?? '');
    $active = (($item['path'] ?? '') === $path) ? ' aria-current="page"' : '';
    $body .= '<a' . $active . ' href="' . $h($link((string) ($item['path'] ?? '#'))) . '">' . $h($t($labelKey)) . '</a>';
}
$body .= '</nav></aside><main class="ow-main"><header class="ow-topbar"><div><span class="ow-pill">' . $h($pageTitle) . '</span><h1>' . $h($pageTitle) . '</h1><p class="ow-muted">' . $h($pageSummary) . '</p></div></header>';

if ($isAuthenticated) {
    $body .= '<section class="ow-current-app-hero" data-context="OWASYS_CURRENT_APP_CONTEXT"><small>' . $h($t('registry.you_are_working_on')) . '</small>';
    if ($currentApp !== null) {
        $body .= '<strong>' . $h((string) ($currentApp['name'] ?? $currentApp['id'] ?? $t('common.unknown'))) . '</strong>' . $renderAppTags($currentApp);
    } else {
        $body .= '<strong>' . $h($t('registry.no_application_selected')) . '</strong><p>' . $h($t('registry.choose_or_create')) . '</p>';
    }
    $body .= '<p><a class="ow-button ow-button-secondary" href="' . $h($link('/applications')) . '">' . $h($t('registry.change_application')) . '</a></p></section>';
}
if ($isAuthenticated && !in_array($state, ['login', 'account'], true)) {
    $currentStateId = is_array($currentState) ? (string) ($currentState['id'] ?? '') : null;
    $body .= $renderMermaidPanel($buildMermaidDiagram($fsmConfig, $currentApp, $currentStateId));
}
if ($currentApp !== null && !in_array($state, ['login', 'account', 'registry'], true)) {
    $body .= '<section class="ow-card ow-context-panel"><h2>' . $h($t('registry.application_context')) . '</h2><p>' . $h($t('registry.context_description')) . '</p>' . $renderAppTags($currentApp) . '<p><a class="ow-button ow-button-secondary" href="' . $h($link('/applications')) . '">' . $h($t('registry.change_application')) . '</a></p></section>';
}

if ($state === 'login') {
    $body .= '<section class="ow-card ow-auth-panel">';
    if ($isAuthenticated) {
        $body .= '<h2>' . $h($t('auth.session_active')) . '</h2><p>' . $h($t('auth.session_active_description')) . '</p><div class="ow-tags"><span>' . $h($t('common.profile')) . ': ' . $h((string) ($user['profile'] ?? $t('common.unknown'))) . '</span><span>' . $h($t('common.mode')) . ': ' . $h((string) ($user['mode'] ?? $t('common.unknown'))) . '</span></div><p><a class="ow-button" href="' . $h($link('/logout')) . '">' . $h($t('auth.logout')) . '</a><a class="ow-button ow-button-secondary" href="' . $h($link('/applications')) . '">' . $h($t('menu.applications')) . '</a></p>';
    } else {
        $body .= '<h2>' . $h($t('auth.sign_in')) . '</h2><p>' . $h($t('auth.sign_in_description')) . '</p>';
        if ($loginErrorKey !== null) {
            $body .= '<p class="ow-login-error">' . $h($t($loginErrorKey)) . '</p>';
        }
        if (!is_file($authStoreFile)) {
            $body .= '<p class="ow-login-warning">' . $h($t('auth.runtime_store_missing')) . '</p>';
        }
        $body .= '<form method="post" class="ow-login-form"><input type="hidden" name="owasys_action" value="password-signin"><label>' . $h($t('auth.username')) . '<input name="owasys_username" autocomplete="username" required></label><label>' . $h($t('auth.password_field')) . '<input name="owasys_password" type="password" autocomplete="current-password" required></label><button class="ow-button" type="submit">' . $h($t('auth.sign_in')) . '</button></form>';
    }
    $body .= '</section>';
}
if ($state === 'account') {
    $body .= '<section class="ow-card ow-auth-panel"><h2>' . $h($t('auth.change_password')) . '</h2><p>' . $h($t('auth.change_password_description')) . '</p>';
    if ($mustChangePassword) {
        $body .= '<p class="ow-login-warning">' . $h($t('auth.password_change_required')) . '</p>';
    }
    if ($passwordChangeErrorKey !== null) {
        $body .= '<p class="ow-login-error">' . $h($t($passwordChangeErrorKey)) . '</p>';
    }
    $body .= '<form method="post" class="ow-password-form"><input type="hidden" name="owasys_action" value="change-password"><label>' . $h($t('auth.current_password')) . '<input name="owasys_current_password" type="password" autocomplete="current-password" required></label><label>' . $h($t('auth.new_password')) . '<input name="owasys_new_password" type="password" autocomplete="new-password" minlength="10" required></label><label>' . $h($t('auth.confirm_new_password')) . '<input name="owasys_confirm_password" type="password" autocomplete="new-password" minlength="10" required></label><button class="ow-button" type="submit">' . $h($t('auth.change_password')) . '</button></form></section>';
}
if ($state === 'registry') {
    $entries = (array) ($page['registry_entries'] ?? []);
    $recentEvents = $owasysRegistryRepository->recentEvents(8);
    $body .= '<section class="ow-card ow-context-panel"><h2>' . $h($t('registry.application_context')) . '</h2>';
    if ($registryActionErrorKey !== null) {
        $body .= '<p class="ow-login-error">' . $h($t($registryActionErrorKey)) . '</p>';
    }
    $body .= '<p>' . $h($t('registry.select_instruction')) . '</p>' . $renderAppTags($currentApp);
    if ($currentApp !== null) {
        $body .= '<form method="post" class="ow-inline-form"><input type="hidden" name="owasys_action" value="clear-app-context"><button class="ow-button ow-button-secondary" type="submit">' . $h($t('registry.clear_current_context')) . '</button></form>';
    }
    $body .= '</section>' . $renderAppTree($entries, $currentApp);
    $body .= '<section class="ow-card"><h2>' . $h($t('registry.runtime_sqlite')) . '</h2><div class="ow-tags"><span>' . $h($t('registry.database')) . ': ' . $h((string) ($page['registry_database'] ?? $registryDatabaseRelative)) . '</span><span>' . $h($t('registry.sync_total')) . ': ' . $h((string) ($owasysRegistrySync['total'] ?? '0')) . '</span></div></section>';
    $body .= $renderEvents($recentEvents);
    $body .= '<section class="ow-grid ow-registry-grid">';
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $entryId = (string) ($entry['id'] ?? '');
        $isCurrent = $currentApp !== null && (string) ($currentApp['id'] ?? '') === $entryId;
        $body .= '<article class="ow-card ow-registry-card"><h2>' . $h((string) ($entry['name'] ?? $entryId)) . '</h2><p>' . $h($t('registry.registered_target')) . ': ' . $h((string) ($entry['root_path'] ?? $t('common.unknown'))) . '</p>' . $renderAppTags($entry);
        $body .= '<form method="post" class="ow-inline-form"><input type="hidden" name="owasys_action" value="select-app"><input type="hidden" name="owasys_app_id" value="' . $h($entryId) . '"><button class="ow-button" type="submit">' . $h($isCurrent ? $t('registry.current_application') : $t('registry.work_on_this_app')) . '</button></form></article>';
    }
    $body .= '</section>';
}
if ($state === 'structure' && is_array($currentApplicationInspection)) {
    $body .= $renderInspection($currentApplicationInspection, $structureActionResult, $structureDraftResult, $structureDrafts, $structureActionErrorKey);
}
if (!in_array($state, ['registry', 'login', 'account', 'structure'], true)) {
    $body .= '<section class="ow-grid"><article class="ow-card"><h2>' . $h($t('state.default.section')) . '</h2><p class="ow-muted">' . $h($t('state.default.summary')) . '</p></article></section>';
}
if (!empty($page['contracts'])) {
    $body .= '<section class="ow-card"><h2>' . $h($t('common.contracts')) . '</h2><div class="ow-tags">';
    foreach ((array) $page['contracts'] as $contract) {
        $body .= '<span>' . $h((string) $contract) . '</span>';
    }
    $body .= '</div></section>';
}
if (!empty($page['action_keys']) && is_array($page['action_keys'])) {
    $body .= '<section class="ow-card"><h2>' . $h($t('common.next_actions')) . '</h2>' . $renderList((array) $page['action_keys']) . '</section>';
}
if ($isAuthenticated && isset($statesById[$state])) {
    $_SESSION['owasys_current_state'] = $state;
}
$body .= '</main></div>';

echo '<!doctype html>'
    . '<html lang="' . $h($locale) . '">'
    . '<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<title>' . $h($pageTitle) . ' — ' . $h($t('brand.name')) . '</title>'
    . '<link rel="stylesheet" href="' . $h($asset('/asset/css/owasys.css')) . '">'
    . '<link rel="stylesheet" href="' . $h($asset('/asset/themes/owasys/css/theme.css')) . '">'
    . '</head>'
    . '<body data-opus-dispatch="state-first" data-opus-state="' . $h($state) . '">' . $body
    . '<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>'
    . '<script src="' . $h($asset('/asset/js/owasys.js')) . '"></script>'
    . '<script src="' . $h($asset('/asset/themes/owasys/js/theme.js')) . '"></script>'
    . '</body></html>';
