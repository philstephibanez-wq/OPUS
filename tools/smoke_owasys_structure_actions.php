<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\ApplicationInspector;
use Opus\Owasys\RegistryRepository;

$root = dirname(__DIR__);
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';
$frontFile = $siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'index.php';
$frFile = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR . 'fr.php';
$enFile = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR . 'en.php';
$databaseRelative = 'var/registry/owasys-structure-actions-smoke.sqlite';
$database = $siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $databaseRelative);
$seedFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'registry.seed.json';

function owasys_structure_actions_remove_database(string $database): void
{
    @unlink($database);
    @unlink($database . '-shm');
    @unlink($database . '-wal');
    $dir = dirname($database);
    if (is_dir($dir) && count(scandir($dir) ?: []) === 2) {
        @rmdir($dir);
    }
}

foreach ([__FILE__, $frontFile, $frFile, $enFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "OWASYS_STRUCTURE_ACTIONS_REQUIRED_FILE_MISSING: {$file}\n");
        exit(1);
    }
    $output = [];
    $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, "OWASYS_STRUCTURE_ACTIONS_PARSE_ERROR: {$file}\n" . implode("\n", $output) . "\n");
        exit(1);
    }
}

owasys_structure_actions_remove_database($database);

try {
    $repository = RegistryRepository::forOwasysSite($siteRoot, $root, $databaseRelative);
    $sync = $repository->synchronize($seedFile);
    if (($sync['contract'] ?? null) !== RegistryRepository::CONTRACT) {
        fwrite(STDERR, "OWASYS_STRUCTURE_ACTIONS_SYNC_INVALID\n");
        exit(1);
    }

    $inspector = ApplicationInspector::forOpusRoot($root);
    $inspection = $inspector->inspectEntry([
        'id' => 'demo-app',
        'name' => 'Demo OPUS Application',
        'root_path' => 'sites/demo-app',
    ]);

    $result = $repository->recordStructureValidation([
        'id' => 'demo-app',
        'name' => 'Demo OPUS Application',
        'root_path' => 'sites/demo-app',
    ], $inspection, 'structure-smoke');

    if (($result['contract'] ?? null) !== 'OWASYS_STRUCTURE_VALIDATION_RESULT_V1' || ($result['status'] ?? null) !== 'valid' || ($result['application_id'] ?? null) !== 'demo-app') {
        fwrite(STDERR, "OWASYS_STRUCTURE_ACTIONS_RESULT_INVALID\n");
        exit(1);
    }
    if ((int) ($result['state_count'] ?? 0) !== 2 || (int) ($result['route_count'] ?? 0) !== 2 || (int) ($result['transition_count'] ?? 0) !== 2) {
        fwrite(STDERR, "OWASYS_STRUCTURE_ACTIONS_RESULT_COUNTS_INVALID\n");
        exit(1);
    }

    $runtime = $repository->runtimeValue('last_structure_validation');
    if (!is_array($runtime) || ($runtime['application_id'] ?? null) !== 'demo-app' || ($runtime['status'] ?? null) !== 'valid') {
        fwrite(STDERR, "OWASYS_STRUCTURE_ACTIONS_RUNTIME_CONTEXT_INVALID\n");
        exit(1);
    }
    if ($repository->eventCount('validate_application') !== 1) {
        fwrite(STDERR, "OWASYS_STRUCTURE_ACTIONS_EVENT_COUNT_INVALID\n");
        exit(1);
    }

    $events = $repository->recentEvents(1);
    $event = $events[0] ?? null;
    if (!is_array($event) || ($event['event_type'] ?? null) !== 'validate_application' || ($event['application_id'] ?? null) !== 'demo-app') {
        fwrite(STDERR, "OWASYS_STRUCTURE_ACTIONS_RECENT_EVENT_INVALID\n");
        exit(1);
    }

    $entries = $repository->entries();
    $demo = null;
    foreach ($entries as $entry) {
        if (is_array($entry) && ($entry['id'] ?? null) === 'demo-app') {
            $demo = $entry;
            break;
        }
    }
    if (!is_array($demo) || ($demo['status'] ?? null) !== 'validated' || ($demo['source'] ?? null) !== 'inspection') {
        fwrite(STDERR, "OWASYS_STRUCTURE_ACTIONS_REGISTRY_RESYNC_INVALID\n");
        exit(1);
    }

    $front = (string) file_get_contents($frontFile);
    foreach (['validate-current-application', 'recordStructureValidation', 'OWASYS_STRUCTURE_ACTION_RESULT', 'inspection.action.validate_now', 'inspection.validation_result'] as $needle) {
        if (!str_contains($front, $needle)) {
            fwrite(STDERR, "OWASYS_STRUCTURE_ACTIONS_FRONT_MARKER_MISSING: {$needle}\n");
            exit(1);
        }
    }

    foreach (['fr' => $frFile, 'en' => $enFile] as $locale => $file) {
        $messages = require $file;
        if (!is_array($messages)) {
            fwrite(STDERR, "OWASYS_STRUCTURE_ACTIONS_I18N_INVALID: {$locale}\n");
            exit(1);
        }
        foreach (['inspection.action.validate_now', 'inspection.validation_result', 'inspection.validation_valid', 'inspection.validated_at', 'inspection.validated_by', 'registry.events.validate_application'] as $key) {
            if (!isset($messages[$key]) || !is_string($messages[$key]) || trim($messages[$key]) === '') {
                fwrite(STDERR, "OWASYS_STRUCTURE_ACTIONS_I18N_KEY_MISSING: {$locale}:{$key}\n");
                exit(1);
            }
        }
    }
} finally {
    owasys_structure_actions_remove_database($database);
}

if (is_file($database)) {
    fwrite(STDERR, "OWASYS_STRUCTURE_ACTIONS_DATABASE_CLEANUP_FAILED\n");
    exit(1);
}

echo "OWASYS_STRUCTURE_ACTIONS_SMOKE_OK\n";
