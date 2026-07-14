<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$frontFile = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys' . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'index.php';
$patcherFile = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'owasys_apply_runtime_fsm_patch.php';
$httpSmokeFile = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'smoke_owasys_runtime_fsm_http.php';
$composerFile = $root . DIRECTORY_SEPARATOR . 'composer.json';

foreach ([$frontFile, $httpSmokeFile, $composerFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "OWASYS_RUNTIME_FSM_REQUIRED_FILE_MISSING: {$file}\n");
        exit(1);
    }
}

if (is_file($patcherFile)) {
    fwrite(STDERR, "OWASYS_RUNTIME_FSM_PATCHER_STILL_PRESENT\n");
    exit(1);
}

foreach ([$frontFile, $httpSmokeFile] as $file) {
    $output = [];
    $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, "OWASYS_RUNTIME_FSM_PARSE_ERROR: {$file}\n" . implode("\n", $output) . "\n");
        exit(1);
    }
}

$front = (string) file_get_contents($frontFile);
$requiredMarkers = [
    'use Opus\\Fsm\\FsmSiteLoader;',
    '$opusRoot = dirname(dirname($siteRoot));',
    'OWASYS_COMPOSER_AUTOLOAD_MISSING',
    '$owasysFsmProcessor = FsmSiteLoader::processorForSite($opusRoot, \'owasys\');',
    'OWASYS_RUNTIME_FSM_PROCESSOR_INVALID',
    '$routeForTransition = static function',
    'OWASYS_RUNTIME_FSM_TRANSITION_ROUTE_MISSING',
    '$runtimeCurrentState = static function',
    '$redirectAfterTransition = static function',
    'transition($runtimeCurrentState(), \'logout\'',
    'transition(\'login\', \'password_change_required\'',
    'transition(\'login\', \'login_success\'',
    'transition(\'account\', \'password_changed\'',
    'transition(\'registry\', \'select_app\'',
    'transition(\'registry\', \'clear_app_context\'',
    'transition(\'registry\', \'create_new_app\'',
    'transition($state, \'change_app\'',
    '$_SESSION[\'owasys_current_state\'] = $state;',
];
foreach ($requiredMarkers as $needle) {
    if (!str_contains($front, $needle)) {
        fwrite(STDERR, "OWASYS_RUNTIME_FSM_FRONT_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

$forbiddenRedirects = [
    '$redirect(\'/structure\')',
    '$redirect(\'/applications\')',
    '$redirect(\'/build\')',
];
foreach ($forbiddenRedirects as $forbidden) {
    if (str_contains($front, $forbidden)) {
        fwrite(STDERR, "OWASYS_RUNTIME_FSM_FORBIDDEN_DIRECT_REDIRECT_PRESENT: {$forbidden}\n");
        exit(1);
    }
}

$httpSmoke = (string) file_get_contents($httpSmokeFile);
foreach (['OWASYS_RUNTIME_FSM_HTTP_SMOKE_OK', 'OWASYS_RUNTIME_FSM_HTTP_LOGIN_REDIRECT_INVALID', 'OWASYS_RUNTIME_FSM_HTTP_SELECT_APP_REDIRECT_INVALID'] as $needle) {
    if (!str_contains($httpSmoke, $needle)) {
        fwrite(STDERR, "OWASYS_RUNTIME_FSM_HTTP_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

$composer = json_decode((string) file_get_contents($composerFile), true);
if (!is_array($composer)) {
    fwrite(STDERR, "OWASYS_RUNTIME_FSM_COMPOSER_INVALID\n");
    exit(1);
}
$scripts = is_array($composer['scripts'] ?? null) ? $composer['scripts'] : [];
if (array_key_exists('owasys:apply-runtime-fsm-patch', $scripts)) {
    fwrite(STDERR, "OWASYS_RUNTIME_FSM_COMPOSER_PATCHER_SCRIPT_STILL_PRESENT\n");
    exit(1);
}
if (($scripts['owasys:smoke-runtime-fsm'] ?? null) !== '@php tools/smoke_owasys_runtime_fsm.php') {
    fwrite(STDERR, "OWASYS_RUNTIME_FSM_COMPOSER_SMOKE_SCRIPT_MISSING\n");
    exit(1);
}
if (($scripts['owasys:smoke-runtime-fsm-http'] ?? null) !== '@php tools/smoke_owasys_runtime_fsm_http.php') {
    fwrite(STDERR, "OWASYS_RUNTIME_FSM_COMPOSER_HTTP_SMOKE_SCRIPT_MISSING\n");
    exit(1);
}

echo "OWASYS_RUNTIME_FSM_SMOKE_OK\n";
