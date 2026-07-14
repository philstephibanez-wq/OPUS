<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';
$siteFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json';
$fsmFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'owasys-navigation.fsm.json';
$schemaFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'registry.schema.sql';
$frontFile = $siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'index.php';
$localRoot = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'local';
$frFile = $localRoot . DIRECTORY_SEPARATOR . 'fr.php';
$enFile = $localRoot . DIRECTORY_SEPARATOR . 'en.php';

foreach ([$siteFile, $fsmFile, $schemaFile, $frontFile, $frFile, $enFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "OWASYS_NAVIGATION_FSM_REQUIRED_FILE_MISSING: {$file}\n");
        exit(1);
    }
}

foreach ([$frontFile, $frFile, $enFile] as $phpFile) {
    $lintCommand = PHP_BINARY . ' -l ' . escapeshellarg($phpFile) . ' 2>&1';
    $lintOutput = [];
    $lintCode = 0;
    exec($lintCommand, $lintOutput, $lintCode);
    if ($lintCode !== 0) {
        fwrite(STDERR, "OWASYS_NAVIGATION_FSM_PARSE_ERROR: {$phpFile}\n" . implode("\n", $lintOutput) . "\n");
        exit(1);
    }
}

$site = json_decode((string) file_get_contents($siteFile), true);
$fsm = json_decode((string) file_get_contents($fsmFile), true);
if (!is_array($site) || !is_array($fsm)) {
    fwrite(STDERR, "OWASYS_NAVIGATION_FSM_JSON_INVALID\n");
    exit(1);
}

if (($fsm['contract'] ?? null) !== 'OWASYS_NAVIGATION_FSM_V1') {
    fwrite(STDERR, "OWASYS_NAVIGATION_FSM_CONTRACT_INVALID\n");
    exit(1);
}

if (($site['navigation']['fsm'] ?? null) !== 'config/owasys-navigation.fsm.json') {
    fwrite(STDERR, "OWASYS_NAVIGATION_FSM_SITE_POINTER_INVALID\n");
    exit(1);
}

if (($fsm['source_of_truth'] ?? null) !== 'config') {
    fwrite(STDERR, "OWASYS_NAVIGATION_FSM_SOURCE_INVALID\n");
    exit(1);
}

if (($site['states_root'] ?? null) !== 'application/states' || ($site['dispatch_model'] ?? null) !== 'state-first') {
    fwrite(STDERR, "OWASYS_NAVIGATION_FSM_SITE_DISPATCH_INVALID\n");
    exit(1);
}

$runtime = is_array($fsm['runtime_state'] ?? null) ? $fsm['runtime_state'] : [];
if (($runtime['database'] ?? null) !== 'var/registry/owasys.sqlite') {
    fwrite(STDERR, "OWASYS_NAVIGATION_FSM_RUNTIME_DATABASE_INVALID\n");
    exit(1);
}

$states = [];
foreach ((array) ($fsm['states'] ?? []) as $state) {
    if (is_array($state) && isset($state['id'])) {
        $states[(string) $state['id']] = $state;
    }
}

foreach (['login', 'registry', 'structure', 'data', 'workflows', 'security', 'build'] as $stateId) {
    if (!isset($states[$stateId])) {
        fwrite(STDERR, "OWASYS_NAVIGATION_FSM_STATE_MISSING: {$stateId}\n");
        exit(1);
    }
}

foreach (['structure', 'data', 'workflows', 'security'] as $stateId) {
    if (($states[$stateId]['requires_current_app'] ?? null) !== true) {
        fwrite(STDERR, "OWASYS_NAVIGATION_FSM_CURRENT_APP_GUARD_INVALID: {$stateId}\n");
        exit(1);
    }
}

$transitionIndex = [];
foreach ((array) ($fsm['transitions'] ?? []) as $transition) {
    if (is_array($transition)) {
        $transitionIndex[(string) ($transition['from'] ?? '') . '|' . (string) ($transition['event'] ?? '') . '|' . (string) ($transition['to'] ?? '')] = true;
    }
}
foreach (['registry|select_app|structure', 'registry|create_new_app|build', 'structure|open_data|data', 'security|open_build|build'] as $expected) {
    if (!isset($transitionIndex[$expected])) {
        fwrite(STDERR, "OWASYS_NAVIGATION_FSM_TRANSITION_MISSING: {$expected}\n");
        exit(1);
    }
}

$schema = (string) file_get_contents($schemaFile);
foreach (['owasys_runtime_context', 'owasys_transition_history', 'owasys_transition_drafts'] as $table) {
    if (!str_contains($schema, $table)) {
        fwrite(STDERR, "OWASYS_NAVIGATION_FSM_SCHEMA_TABLE_MISSING: {$table}\n");
        exit(1);
    }
}

$requiredI18nKeys = [
    'mermaid.title',
    'mermaid.description',
    'registry.application_tree',
    'registry.current_application',
    'registry.you_are_working_on',
    'state.registry.title',
    'state.structure.title',
];
foreach (['fr' => $frFile, 'en' => $enFile] as $locale => $file) {
    $messages = require $file;
    if (!is_array($messages)) {
        fwrite(STDERR, "OWASYS_NAVIGATION_FSM_I18N_MESSAGES_INVALID: {$locale}\n");
        exit(1);
    }
    foreach ($requiredI18nKeys as $key) {
        if (!isset($messages[$key]) || !is_string($messages[$key]) || trim($messages[$key]) === '') {
            fwrite(STDERR, "OWASYS_NAVIGATION_FSM_I18N_KEY_MISSING: {$locale}:{$key}\n");
            exit(1);
        }
    }
}

$front = (string) file_get_contents($frontFile);
foreach (['OWASYS_NAVIGATION_FSM_V1', '$statesByRoute', '$requiresCurrentApp', 'OWASYS_REGISTRY_APP_TREE', 'application/default/local', '$t = static function', 'mermaid.title', 'registry.application_tree'] as $needle) {
    if (!str_contains($front, $needle)) {
        fwrite(STDERR, "OWASYS_NAVIGATION_FSM_FRONT_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

foreach (['Visual FSM navigation', 'Application tree</h2>', 'YOU ARE WORKING ON</small>'] as $forbidden) {
    if (str_contains($front, $forbidden)) {
        fwrite(STDERR, "OWASYS_NAVIGATION_FSM_HARDCODED_UI_LITERAL_PRESENT: {$forbidden}\n");
        exit(1);
    }
}

echo "OWASYS_NAVIGATION_FSM_SMOKE_OK\n";