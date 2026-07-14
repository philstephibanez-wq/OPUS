<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$frontFile = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys' . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'index.php';

if (!is_file($frontFile)) {
    fwrite(STDERR, "OWASYS_RUNTIME_FSM_FRONT_MISSING\n");
    exit(1);
}

$front = (string) file_get_contents($frontFile);
$original = $front;

$replaceOnce = static function (string $haystack, string $needle, string $replacement, string $error): string {
    $count = substr_count($haystack, $needle);
    if ($count !== 1) {
        fwrite(STDERR, $error . ': ' . $count . "\n");
        exit(1);
    }
    return str_replace($needle, $replacement, $haystack);
};

$replaceIfMissing = static function (string $haystack, string $marker, string $needle, string $replacement, string $error) use ($replaceOnce): string {
    if (str_contains($haystack, $marker)) {
        return $haystack;
    }
    return $replaceOnce($haystack, $needle, $replacement, $error);
};

$front = $replaceIfMissing(
    $front,
    <<<'PHP'
use Opus\Fsm\FsmSiteLoader;
PHP,
    <<<'PHP'
declare(strict_types=1);

/**
PHP,
    <<<'PHP'
declare(strict_types=1);

use Opus\Fsm\FsmSiteLoader;

/**
PHP,
    'OWASYS_RUNTIME_FSM_USE_INSERT_FAILED'
);

$front = $replaceIfMissing(
    $front,
    <<<'PHP'
$opusRoot = dirname(dirname($siteRoot));
PHP,
    <<<'PHP'
$siteRoot = dirname(__DIR__);
$configFile = $siteRoot . '/config/routes.json';
PHP,
    <<<'PHP'
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
PHP,
    'OWASYS_RUNTIME_FSM_AUTOLOAD_INSERT_FAILED'
);

$front = $replaceIfMissing(
    $front,
    <<<'PHP'
$owasysFsmProcessor = FsmSiteLoader::processorForSite($opusRoot, 'owasys');
PHP,
    <<<'PHP'
if (!is_array($fsmConfig) || ($fsmConfig['contract'] ?? null) !== 'OWASYS_NAVIGATION_FSM_V1') {
    http_response_code(500);
    echo 'OWASYS_NAVIGATION_FSM_INVALID';
    exit;
}

$authConfig = is_array($siteConfig['auth'] ?? null) ? $siteConfig['auth'] : [];
PHP,
    <<<'PHP'
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
PHP,
    'OWASYS_RUNTIME_FSM_PROCESSOR_INSERT_FAILED'
);

$front = $replaceIfMissing(
    $front,
    <<<'PHP'
OWASYS_RUNTIME_FSM_TRANSITION_ROUTE_MISSING
PHP,
    <<<'PHP'
foreach ((array) ($fsmConfig['states'] ?? []) as $state) {
    if (!is_array($state)) {
        continue;
    }
    $stateId = (string) ($state['id'] ?? '');
    if ($stateId === '') {
        continue;
    }
    $statesById[$stateId] = $state;
    $stateRoute = (string) ($state['route'] ?? '');
    if ($stateRoute !== '') {
        $statesByRoute[$stateRoute] = $state;
    }
}

$loginError = null;
PHP,
    <<<'PHP'
foreach ((array) ($fsmConfig['states'] ?? []) as $state) {
    if (!is_array($state)) {
        continue;
    }
    $stateId = (string) ($state['id'] ?? '');
    if ($stateId === '') {
        continue;
    }
    $statesById[$stateId] = $state;
    $stateRoute = (string) ($state['route'] ?? '');
    if ($stateRoute !== '') {
        $statesByRoute[$stateRoute] = $state;
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

$loginError = null;
PHP,
    'OWASYS_RUNTIME_FSM_HELPERS_INSERT_FAILED'
);

$front = $replaceIfMissing(
    $front,
    <<<'PHP'
transition($runtimeCurrentState(), 'logout'
PHP,
    <<<'PHP'
if ($path === '/logout') {
    unset($_SESSION['owasys_user'], $_SESSION['owasys_current_app']);
    session_regenerate_id(true);
    $redirect('/login');
}
PHP,
    <<<'PHP'
if ($path === '/logout') {
    $transition = $owasysFsmProcessor->transition($runtimeCurrentState(), 'logout');
    unset($_SESSION['owasys_user'], $_SESSION['owasys_current_app'], $_SESSION['owasys_current_state']);
    session_regenerate_id(true);
    $redirectAfterTransition($transition);
}
PHP,
    'OWASYS_RUNTIME_FSM_LOGOUT_REPLACE_FAILED'
);

$front = $replaceIfMissing(
    $front,
    <<<'PHP'
transition('login', 'login_success'
PHP,
    <<<'PHP'
            $redirect(($_SESSION['owasys_user']['must_change_password'] ?? false) === true ? '/account/password' : '/applications');
PHP,
    <<<'PHP'
            $transition = ($_SESSION['owasys_user']['must_change_password'] ?? false) === true
                ? $owasysFsmProcessor->transition('login', 'password_change_required', ['must_change_password' => true])
                : $owasysFsmProcessor->transition('login', 'login_success');
            $redirectAfterTransition($transition);
PHP,
    'OWASYS_RUNTIME_FSM_LOGIN_REPLACE_FAILED'
);

$front = $replaceIfMissing(
    $front,
    <<<'PHP'
transition('account', 'password_changed'
PHP,
    <<<'PHP'
        $_SESSION['owasys_user']['password_changed_at'] = $candidate['password_changed_at'];
        $redirect('/applications');
PHP,
    <<<'PHP'
        $_SESSION['owasys_user']['password_changed_at'] = $candidate['password_changed_at'];
        $transition = $owasysFsmProcessor->transition('account', 'password_changed');
        $redirectAfterTransition($transition);
PHP,
    'OWASYS_RUNTIME_FSM_PASSWORD_REPLACE_FAILED'
);

$front = $replaceIfMissing(
    $front,
    <<<'PHP'
transition('registry', 'select_app'
PHP,
    <<<'PHP'
if ($state === 'registry' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
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
PHP,
    <<<'PHP'
if ($state === 'registry' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['owasys_action'] ?? '');
    if ($action === 'select-app') {
        $appId = trim((string) ($_POST['owasys_app_id'] ?? ''));
        $entry = $findRegistryEntry((array) ($page['registry_entries'] ?? []), $appId);
        if ($entry === null) {
            $registryActionError = 'Selected application is not registered.';
        } else {
            $transition = $owasysFsmProcessor->transition('registry', 'select_app', [
                'app_exists' => true,
                'registry_entry' => $entry,
                'selected_app' => $appId,
            ]);
            $_SESSION['owasys_current_app'] = $entry;
            $redirectAfterTransition($transition);
        }
    } elseif ($action === 'clear-app-context') {
        $transition = $owasysFsmProcessor->transition('registry', 'clear_app_context');
        unset($_SESSION['owasys_current_app']);
        $redirectAfterTransition($transition);
    } elseif ($action === 'create-new-app') {
        $transition = $owasysFsmProcessor->transition('registry', 'create_new_app');
        unset($_SESSION['owasys_current_app']);
        $redirectAfterTransition($transition);
    } else {
        http_response_code(400);
        echo 'OWASYS_REGISTRY_ACTION_INVALID';
        exit;
    }
}
PHP,
    'OWASYS_RUNTIME_FSM_REGISTRY_REPLACE_FAILED'
);

$front = $replaceIfMissing(
    $front,
    <<<'PHP'
$_SESSION['owasys_current_state'] = $state;
PHP,
    <<<'PHP'
$body .= '</main></div>';

echo '<!doctype html>'
PHP,
    <<<'PHP'
if ($isAuthenticated && isset($statesById[$state])) {
    $_SESSION['owasys_current_state'] = $state;
}

$body .= '</main></div>';

echo '<!doctype html>'
PHP,
    'OWASYS_RUNTIME_FSM_STATE_SESSION_INSERT_FAILED'
);

if ($front === $original) {
    echo "OWASYS_RUNTIME_FSM_PATCH_NOOP\n";
    exit(0);
}

if (file_put_contents($frontFile, $front) === false) {
    fwrite(STDERR, "OWASYS_RUNTIME_FSM_PATCH_WRITE_FAILED\n");
    exit(1);
}

echo "OWASYS_RUNTIME_FSM_PATCH_OK\n";
