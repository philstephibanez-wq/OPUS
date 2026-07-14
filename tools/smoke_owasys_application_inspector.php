<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\ApplicationInspector;

$root = dirname(__DIR__);
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';
$frontFile = $siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'index.php';
$structureView = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'states' . DIRECTORY_SEPARATOR . 'structure' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'index.php';
$frFile = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR . 'fr.php';
$enFile = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR . 'en.php';

foreach ([$frontFile, $structureView, $frFile, $enFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "OWASYS_APPLICATION_INSPECTOR_REQUIRED_FILE_MISSING: {$file}\n");
        exit(1);
    }
}

foreach ([__FILE__, $frontFile, $structureView] as $file) {
    $output = [];
    $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, "OWASYS_APPLICATION_INSPECTOR_PARSE_ERROR: {$file}\n" . implode("\n", $output) . "\n");
        exit(1);
    }
}

$inspector = ApplicationInspector::forOpusRoot($root);
$inspection = $inspector->inspectEntry([
    'id' => 'demo-app',
    'name' => 'Demo OPUS Application',
    'root_path' => 'sites/demo-app',
]);

if (($inspection['inspection_contract'] ?? null) !== ApplicationInspector::CONTRACT) {
    fwrite(STDERR, "OWASYS_APPLICATION_INSPECTOR_CONTRACT_INVALID\n");
    exit(1);
}
if (($inspection['site_id'] ?? null) !== 'demo-app' || ($inspection['root_path'] ?? null) !== 'sites/demo-app') {
    fwrite(STDERR, "OWASYS_APPLICATION_INSPECTOR_ID_INVALID\n");
    exit(1);
}
if (($inspection['states_root'] ?? null) !== 'application/states' || ($inspection['dispatch_model'] ?? null) !== 'state-first') {
    fwrite(STDERR, "OWASYS_APPLICATION_INSPECTOR_STATE_CONTRACT_INVALID\n");
    exit(1);
}
if (($inspection['fsm_relative_path'] ?? null) !== 'config/application.fsm.json' || ($inspection['fsm_contract'] ?? null) !== 'OPUS_APPLICATION_FSM_V1') {
    fwrite(STDERR, "OWASYS_APPLICATION_INSPECTOR_FSM_INVALID\n");
    exit(1);
}
if (($inspection['route_count'] ?? 0) !== 2 || ($inspection['state_count'] ?? 0) !== 2 || ($inspection['transition_count'] ?? 0) !== 2) {
    fwrite(STDERR, "OWASYS_APPLICATION_INSPECTOR_COUNTS_INVALID\n");
    exit(1);
}

$stateIds = [];
foreach ((array) ($inspection['states'] ?? []) as $state) {
    if (is_array($state)) {
        $stateIds[] = (string) ($state['id'] ?? '');
    }
}
sort($stateIds);
if ($stateIds !== ['articles', 'home']) {
    fwrite(STDERR, "OWASYS_APPLICATION_INSPECTOR_STATES_INVALID\n");
    exit(1);
}

$routeViews = [];
foreach ((array) ($inspection['routes'] ?? []) as $route) {
    if (is_array($route)) {
        $routeViews[] = (string) ($route['view'] ?? '');
    }
}
foreach (['application/states/home/views/index.php', 'application/states/articles/views/index.php'] as $expectedView) {
    if (!in_array($expectedView, $routeViews, true)) {
        fwrite(STDERR, "OWASYS_APPLICATION_INSPECTOR_ROUTE_VIEW_MISSING: {$expectedView}\n");
        exit(1);
    }
}

try {
    $inspector->inspectEntry(['id' => 'bad', 'root_path' => '../bad']);
    fwrite(STDERR, "OWASYS_APPLICATION_INSPECTOR_UNSAFE_ROOT_ACCEPTED\n");
    exit(1);
} catch (RuntimeException $exception) {
    if (!str_contains($exception->getMessage(), 'OWASYS_APPLICATION_INSPECTION_ROOT_PATH_INVALID')) {
        throw $exception;
    }
}

$front = (string) file_get_contents($frontFile);
foreach (['use Opus\\Owasys\\ApplicationInspector;', 'ApplicationInspector::forOpusRoot', 'inspectEntry($currentApp)', 'OWASYS_APPLICATION_INSPECTION', 'inspection.title', 'inspection.states', 'inspection.routes'] as $needle) {
    if (!str_contains($front, $needle)) {
        fwrite(STDERR, "OWASYS_APPLICATION_INSPECTOR_FRONT_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}
foreach (['Selected application inspection</h2>', 'Detected states</h3>', 'Detected routes</h3>'] as $forbidden) {
    if (str_contains($front, $forbidden)) {
        fwrite(STDERR, "OWASYS_APPLICATION_INSPECTOR_HARDCODED_UI_LITERAL_PRESENT: {$forbidden}\n");
        exit(1);
    }
}

$structure = (string) file_get_contents($structureView);
foreach (['OWASYS_APPLICATION_INSPECTION_V1', 'inspection.action.validate', 'inspection.action.open_registry'] as $needle) {
    if (!str_contains($structure, $needle)) {
        fwrite(STDERR, "OWASYS_APPLICATION_INSPECTOR_STRUCTURE_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

foreach (['fr' => $frFile, 'en' => $enFile] as $locale => $file) {
    $messages = require $file;
    if (!is_array($messages)) {
        fwrite(STDERR, "OWASYS_APPLICATION_INSPECTOR_I18N_INVALID: {$locale}\n");
        exit(1);
    }
    foreach (['inspection.title', 'inspection.description', 'inspection.states', 'inspection.routes', 'inspection.transitions', 'inspection.action.validate', 'inspection.action.open_registry'] as $key) {
        if (!isset($messages[$key]) || !is_string($messages[$key]) || trim($messages[$key]) === '') {
            fwrite(STDERR, "OWASYS_APPLICATION_INSPECTOR_I18N_KEY_MISSING: {$locale}:{$key}\n");
            exit(1);
        }
    }
}

echo "OWASYS_APPLICATION_INSPECTOR_SMOKE_OK\n";
