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

if (!str_contains($front, 'use Opus\\Fsm\\FsmSiteLoader;')) {
    $front = $replaceOnce(
        $front,
        "declare(strict_types=1);\n\n/**",
        "declare(strict_types=1);\n\nuse Opus\\Fsm\\FsmSiteLoader;\n\n/**",
        'OWASYS_RUNTIME_FSM_USE_INSERT_FAILED'
    );
}

if (!str_contains($front, '$opusRoot = dirname(dirname($siteRoot));')) {
    $front = $replaceOnce(
        $front,
        "$siteRoot = dirname(__DIR__);\n$configFile = $siteRoot . '/config/routes.json';",
        "$siteRoot = dirname(__DIR__);\n$opusRoot = dirname(dirname($siteRoot));\n$autoload = $opusRoot . '/vendor/autoload.php';\nif (!is_file($autoload)) {\n    http_response_code(500);\n    echo 'OWASYS_COMPOSER_AUTOLOAD_MISSING';\n    exit;\n}\nrequire_once $autoload;\n$configFile = $siteRoot . '/config/routes.json';",
        'OWASYS_RUNTIME_FSM_AUTOLOAD_INSERT_FAILED'
    );
}

if (!str_contains($front, '$owasysFsmProcessor = FsmSiteLoader::processorForSite($opusRoot, \'owasys\');')) {
    $front = $replaceOnce(
        $front,
        "if (!is_array($fsmConfig) || ($fsmConfig['contract'] ?? null) !== 'OWASYS_NAVIGATION_FSM_V1') {\n    http_response_code(500);\n    echo 'OWASYS_NAVIGATION_FSM_INVALID';\n    exit;\n}\n\n$authConfig = is_array($siteConfig['auth'] ?? null) ? $siteConfig['auth'] : [];",
        "if (!is_array($fsmConfig) || ($fsmConfig['contract'] ?? null) !== 'OWASYS_NAVIGATION_FSM_V1') {\n    http_response_code(500);\n    echo 'OWASYS_NAVIGATION_FSM_INVALID';\n    exit;\n}\n\ntry {\n    $owasysFsmProcessor = FsmSiteLoader::processorForSite($opusRoot, 'owasys');\n} catch (Throwable $exception) {\n    http_response_code(500);\n    echo 'OWASYS_RUNTIME_FSM_PROCESSOR_INVALID';\n    exit;\n}\n\n$authConfig = is_array($siteConfig['auth'] ?? null) ? $siteConfig['auth'] : [];",
        'OWASYS_RUNTIME_FSM_PROCESSOR_INSERT_FAILED'
    );
}

if (!str_contains($front, 'OWASYS_RUNTIME_FSM_TRANSITION_ROUTE_MISSING')) {
    $front = $replaceOnce(
        $front,
        "foreach ((array) ($fsmConfig['states'] ?? []) as $state) {\n    if (!is_array($state)) {\n        continue;\n    }\n    $stateId = (string) ($state['id'] ?? '');\n    if ($stateId === '') {\n        continue;\n    }\n    $statesById[$stateId] = $state;\n    $stateRoute = (string) ($state['route'] ?? '');\n    if ($stateRoute !== '') {\n        $statesByRoute[$stateRoute] = $state;\n    }\n}\n\n$loginError = null;",
        "foreach ((array) ($fsmConfig['states'] ?? []) as $state) {\n    if (!is_array($state)) {\n        continue;\n    }\n    $stateId = (string) ($state['id'] ?? '');\n    if ($stateId === '') {\n        continue;\n    }\n    $statesById[$stateId] = $state;\n    $stateRoute = (string) ($state['route'] ?? '');\n    if ($stateRoute !== '') {\n        $statesByRoute[$stateRoute] = $state;\n    }\n}\n\n$routeForTransition = static function (array $transition) use ($statesById): string {\n    $targetState = (string) ($transition['to_state'] ?? '');\n    $target = is_array($statesById[$targetState] ?? null) ? $statesById[$targetState] : [];\n    $route = (string) ($target['route'] ?? '');\n    if ($route === '') {\n        http_response_code(500);\n        echo 'OWASYS_RUNTIME_FSM_TRANSITION_ROUTE_MISSING';\n        exit;\n    }\n    return $route;\n};\n\n$runtimeCurrentState = static function () use ($statesById): string {\n    $candidate = (string) ($_SESSION['owasys_current_state'] ?? 'home');\n    return isset($statesById[$candidate]) ? $candidate : 'home';\n};\n\n$redirectAfterTransition = static function (array $transition) use ($routeForTransition, $redirect): void {\n    $redirect($routeForTransition($transition));\n};\n\n$loginError = null;",
        'OWASYS_RUNTIME_FSM_HELPERS_INSERT_FAILED'
    );
}

$front = $replaceOnce(
    $front,
    "if ($path === '/logout') {\n    unset($_SESSION['owasys_user'], $_SESSION['owasys_current_app']);\n    session_regenerate_id(true);\n    $redirect('/login');\n}",
    "if ($path === '/logout') {\n    $transition = $owasysFsmProcessor->transition($runtimeCurrentState(), 'logout');\n    unset($_SESSION['owasys_user'], $_SESSION['owasys_current_app'], $_SESSION['owasys_current_state']);\n    session_regenerate_id(true);\n    $redirectAfterTransition($transition);\n}",
    'OWASYS_RUNTIME_FSM_LOGOUT_REPLACE_FAILED'
);

$front = $replaceOnce(
    $front,
    "            $redirect(($_SESSION['owasys_user']['must_change_password'] ?? false) === true ? '/account/password' : '/applications');",
    "            $transition = ($_SESSION['owasys_user']['must_change_password'] ?? false) === true\n                ? $owasysFsmProcessor->transition('login', 'password_change_required', ['must_change_password' => true])\n                : $owasysFsmProcessor->transition('login', 'login_success');\n            $redirectAfterTransition($transition);",
    'OWASYS_RUNTIME_FSM_LOGIN_REPLACE_FAILED'
);

$front = $replaceOnce(
    $front,
    "        $_SESSION['owasys_user']['password_changed_at'] = $candidate['password_changed_at'];\n        $redirect('/applications');",
    "        $_SESSION['owasys_user']['password_changed_at'] = $candidate['password_changed_at'];\n        $transition = $owasysFsmProcessor->transition('account', 'password_changed');\n        $redirectAfterTransition($transition);",
    'OWASYS_RUNTIME_FSM_PASSWORD_REPLACE_FAILED'
);

$front = $replaceOnce(
    $front,
    "if ($state === 'registry' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {\n    $action = (string) ($_POST['owasys_action'] ?? '');\n    if ($action === 'select-app') {\n        $appId = trim((string) ($_POST['owasys_app_id'] ?? ''));\n        $entry = $findRegistryEntry((array) ($page['registry_entries'] ?? []), $appId);\n        if ($entry === null) {\n            $registryActionError = 'Selected application is not registered.';\n        } else {\n            $_SESSION['owasys_current_app'] = $entry;\n            $redirect('/structure');\n        }\n    } elseif ($action === 'clear-app-context') {\n        unset($_SESSION['owasys_current_app']);\n        $redirect('/applications');\n    } elseif ($action === 'create-new-app') {\n        unset($_SESSION['owasys_current_app']);\n        $redirect('/build');\n    } else {\n        http_response_code(400);\n        echo 'OWASYS_REGISTRY_ACTION_INVALID';\n        exit;\n    }\n}",
    "if ($state === 'registry' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {\n    $action = (string) ($_POST['owasys_action'] ?? '');\n    if ($action === 'select-app') {\n        $appId = trim((string) ($_POST['owasys_app_id'] ?? ''));\n        $entry = $findRegistryEntry((array) ($page['registry_entries'] ?? []), $appId);\n        if ($entry === null) {\n            $registryActionError = 'Selected application is not registered.';\n        } else {\n            $transition = $owasysFsmProcessor->transition('registry', 'select_app', [\n                'app_exists' => true,\n                'registry_entry' => $entry,\n                'selected_app' => $appId,\n            ]);\n            $_SESSION['owasys_current_app'] = $entry;\n            $redirectAfterTransition($transition);\n        }\n    } elseif ($action === 'clear-app-context') {\n        $transition = $owasysFsmProcessor->transition('registry', 'clear_app_context');\n        unset($_SESSION['owasys_current_app']);\n        $redirectAfterTransition($transition);\n    } elseif ($action === 'create-new-app') {\n        $transition = $owasysFsmProcessor->transition('registry', 'create_new_app');\n        unset($_SESSION['owasys_current_app']);\n        $redirectAfterTransition($transition);\n    } else {\n        http_response_code(400);\n        echo 'OWASYS_REGISTRY_ACTION_INVALID';\n        exit;\n    }\n}",
    'OWASYS_RUNTIME_FSM_REGISTRY_REPLACE_FAILED'
);

if (!str_contains($front, "$_SESSION['owasys_current_state'] = $state;")) {
    $front = $replaceOnce(
        $front,
        "$body .= '</main></div>';\n\necho '<!doctype html>'",
        "if ($isAuthenticated && isset($statesById[$state])) {\n    $_SESSION['owasys_current_state'] = $state;\n}\n\n$body .= '</main></div>';\n\necho '<!doctype html>'",
        'OWASYS_RUNTIME_FSM_STATE_SESSION_INSERT_FAILED'
    );
}

if ($front === $original) {
    echo "OWASYS_RUNTIME_FSM_PATCH_NOOP\n";
    exit(0);
}

if (file_put_contents($frontFile, $front) === false) {
    fwrite(STDERR, "OWASYS_RUNTIME_FSM_PATCH_WRITE_FAILED\n");
    exit(1);
}

echo "OWASYS_RUNTIME_FSM_PATCH_OK\n";
