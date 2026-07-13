<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$sitesRoot = $root . DIRECTORY_SEPARATOR . 'sites';
if (!is_dir($sitesRoot)) {
    fwrite(STDERR, "OPUS_FSM_CONTRACT_SITES_ROOT_MISSING\n");
    exit(1);
}

$siteDirectories = array_values(array_filter(glob($sitesRoot . DIRECTORY_SEPARATOR . '*') ?: [], 'is_dir'));
if ($siteDirectories === []) {
    fwrite(STDERR, "OPUS_FSM_CONTRACT_NO_SITES\n");
    exit(1);
}

$allowedContracts = [
    'OPUS_APPLICATION_FSM_V1' => true,
    'OPUS_FSM_REGISTRY_V1' => true,
    'OWASYS_NAVIGATION_FSM_V1' => true,
];

foreach ($siteDirectories as $siteDirectory) {
    $siteId = basename($siteDirectory);
    $configDirectory = $siteDirectory . DIRECTORY_SEPARATOR . 'config';
    $siteConfigFile = $configDirectory . DIRECTORY_SEPARATOR . 'site.json';
    if (!is_file($siteConfigFile)) {
        continue;
    }

    $siteConfig = json_decode((string) file_get_contents($siteConfigFile), true);
    if (!is_array($siteConfig)) {
        fwrite(STDERR, "OPUS_FSM_CONTRACT_SITE_JSON_INVALID: {$siteId}\n");
        exit(1);
    }

    $candidates = [];
    $navigation = is_array($siteConfig['navigation'] ?? null) ? $siteConfig['navigation'] : [];
    $navigationFsm = str_replace('\\', '/', (string) ($navigation['fsm'] ?? ''));
    if ($navigationFsm !== '' && !str_contains($navigationFsm, '..')) {
        $candidates[] = $siteDirectory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, trim($navigationFsm, '/'));
    }

    $candidates[] = $configDirectory . DIRECTORY_SEPARATOR . 'application.fsm.json';
    $candidates[] = $configDirectory . DIRECTORY_SEPARATOR . 'fsm.json';
    $candidates[] = $configDirectory . DIRECTORY_SEPARATOR . 'owasys-navigation.fsm.json';
    $candidates = array_values(array_unique($candidates));

    $found = null;
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $found = $candidate;
            break;
        }
    }

    if ($found === null) {
        fwrite(STDERR, "OPUS_FSM_CONTRACT_MISSING: {$siteId}\n");
        exit(1);
    }

    $fsm = json_decode((string) file_get_contents($found), true);
    if (!is_array($fsm)) {
        fwrite(STDERR, "OPUS_FSM_CONTRACT_JSON_INVALID: {$siteId}\n");
        exit(1);
    }

    $contract = (string) ($fsm['contract'] ?? '');
    if (!isset($allowedContracts[$contract])) {
        fwrite(STDERR, "OPUS_FSM_CONTRACT_INVALID: {$siteId}:{$contract}\n");
        exit(1);
    }

    $states = $fsm['states'] ?? null;
    if (!is_array($states) || $states === []) {
        fwrite(STDERR, "OPUS_FSM_CONTRACT_STATES_MISSING: {$siteId}\n");
        exit(1);
    }

    if (!array_key_exists('initial_state', $fsm)) {
        fwrite(STDERR, "OPUS_FSM_CONTRACT_INITIAL_STATE_MISSING: {$siteId}\n");
        exit(1);
    }

    if (!array_key_exists('transitions', $fsm) || !is_array($fsm['transitions'])) {
        fwrite(STDERR, "OPUS_FSM_CONTRACT_TRANSITIONS_MISSING: {$siteId}\n");
        exit(1);
    }
}

echo "OPUS_FSM_CONTRACT_SMOKE_OK\n";
