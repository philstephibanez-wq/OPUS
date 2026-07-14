<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\ApplicationInspector;
use Opus\Owasys\RegistryRepository;
use Opus\Owasys\StructureDraftRepository;

$root = dirname(__DIR__);
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';
$databaseRelative = 'var/registry/owasys-structure-drafts-smoke.sqlite';
$database = $siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $databaseRelative);
$seedFile = $siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'registry.seed.json';
$demoFaqStateRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'demo-app' . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'states' . DIRECTORY_SEPARATOR . 'faq';

function owasys_structure_drafts_remove_database(string $database): void
{
    @unlink($database);
    @unlink($database . '-shm');
    @unlink($database . '-wal');
    $dir = dirname($database);
    if (is_dir($dir) && count(scandir($dir) ?: []) === 2) {
        @rmdir($dir);
    }
}

foreach ([__FILE__] as $file) {
    $output = [];
    $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, "OWASYS_STRUCTURE_DRAFTS_PARSE_ERROR: {$file}\n" . implode("\n", $output) . "\n");
        exit(1);
    }
}

owasys_structure_drafts_remove_database($database);
if (is_dir($demoFaqStateRoot)) {
    fwrite(STDERR, "OWASYS_STRUCTURE_DRAFTS_PREEXISTING_FAQ_STATE_ROOT\n");
    exit(1);
}

try {
    $registry = RegistryRepository::forOwasysSite($siteRoot, $root, $databaseRelative);
    $registry->synchronize($seedFile);

    $inspector = ApplicationInspector::forOpusRoot($root);
    $entry = [
        'id' => 'demo-app',
        'name' => 'Demo OPUS Application',
        'root_path' => 'sites/demo-app',
    ];
    $inspection = $inspector->inspectEntry($entry);

    $draftRepository = StructureDraftRepository::forRegistry($registry);
    $draft = $draftRepository->prepareAddStateDraft($entry, $inspection, [
        'state_id' => 'faq',
        'route_path' => '/faq',
        'title_key' => 'state.faq.title',
        'event_name' => 'open_faq',
    ], 'structure-draft-smoke');

    if (($draft['contract'] ?? null) !== StructureDraftRepository::ADD_STATE_DRAFT_CONTRACT || ($draft['status'] ?? null) !== 'draft') {
        fwrite(STDERR, "OWASYS_STRUCTURE_DRAFTS_DRAFT_CONTRACT_INVALID\n");
        exit(1);
    }
    if (($draft['state_id'] ?? null) !== 'faq' || ($draft['route_path'] ?? null) !== '/faq' || ($draft['disk_mutation'] ?? true) !== false) {
        fwrite(STDERR, "OWASYS_STRUCTURE_DRAFTS_DRAFT_PAYLOAD_INVALID\n");
        exit(1);
    }
    if (is_dir($demoFaqStateRoot)) {
        fwrite(STDERR, "OWASYS_STRUCTURE_DRAFTS_MUTATED_DISK\n");
        exit(1);
    }

    $recent = $draftRepository->recentDrafts('demo-app', 3);
    if (!isset($recent[0]) || ($recent[0]['state_id'] ?? null) !== 'faq' || ($recent[0]['route_path'] ?? null) !== '/faq') {
        fwrite(STDERR, "OWASYS_STRUCTURE_DRAFTS_RECENT_INVALID\n");
        exit(1);
    }

    $lastDraft = $registry->runtimeValue('last_structure_draft');
    if (!is_array($lastDraft) || ($lastDraft['state_id'] ?? null) !== 'faq') {
        fwrite(STDERR, "OWASYS_STRUCTURE_DRAFTS_RUNTIME_CONTEXT_INVALID\n");
        exit(1);
    }
    if ($registry->eventCount('draft_add_state') !== 1) {
        fwrite(STDERR, "OWASYS_STRUCTURE_DRAFTS_EVENT_COUNT_INVALID\n");
        exit(1);
    }

    try {
        $draftRepository->prepareAddStateDraft($entry, $inspection, [
            'state_id' => 'home',
            'route_path' => '/home-copy',
        ]);
        fwrite(STDERR, "OWASYS_STRUCTURE_DRAFTS_DUPLICATE_STATE_ACCEPTED\n");
        exit(1);
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'OWASYS_STRUCTURE_DRAFT_STATE_ALREADY_EXISTS')) {
            throw $exception;
        }
    }

    try {
        $draftRepository->prepareAddStateDraft($entry, $inspection, [
            'state_id' => 'badroute',
            'route_path' => '/../bad',
        ]);
        fwrite(STDERR, "OWASYS_STRUCTURE_DRAFTS_UNSAFE_ROUTE_ACCEPTED\n");
        exit(1);
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'OWASYS_STRUCTURE_DRAFT_ROUTE_PATH_INVALID')) {
            throw $exception;
        }
    }
} finally {
    owasys_structure_drafts_remove_database($database);
}

if (is_file($database)) {
    fwrite(STDERR, "OWASYS_STRUCTURE_DRAFTS_DATABASE_CLEANUP_FAILED\n");
    exit(1);
}
if (is_dir($demoFaqStateRoot)) {
    fwrite(STDERR, "OWASYS_STRUCTURE_DRAFTS_DISK_CLEANUP_FAILED\n");
    exit(1);
}

echo "OWASYS_STRUCTURE_DRAFTS_SMOKE_OK\n";
