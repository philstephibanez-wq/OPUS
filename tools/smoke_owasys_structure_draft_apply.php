<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\ApplicationInspector;
use Opus\Owasys\RegistryRepository;
use Opus\Owasys\StructureDraftApplier;
use Opus\Owasys\StructureDraftRepository;
use Opus\Owasys\StructureDraftWritePlanner;

$root = dirname(__DIR__);
$owasysSiteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';
$sourceSiteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'demo-app';
$smokeSiteId = 'owasys-apply-draft-smoke-demo';
$smokeSiteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . $smokeSiteId;
$databaseRelative = 'var/registry/owasys-structure-draft-apply-smoke.sqlite';
$database = $owasysSiteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $databaseRelative);
$seedFile = $owasysSiteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'registry.seed.json';

function owasys_structure_apply_remove_tree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
}

function owasys_structure_apply_copy_tree(string $source, string $target): void
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $destination = $target . DIRECTORY_SEPARATOR . $relative;
        if ($item->isDir()) {
            if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
                throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_COPY_DIR_FAILED: ' . $destination);
            }
        } else {
            $parent = dirname($destination);
            if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
                throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_COPY_PARENT_FAILED: ' . $parent);
            }
            if (!copy($item->getPathname(), $destination)) {
                throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_COPY_FILE_FAILED: ' . $destination);
            }
        }
    }
}

function owasys_structure_apply_remove_database(string $database): void
{
    @unlink($database);
    @unlink($database . '-shm');
    @unlink($database . '-wal');
    $dir = dirname($database);
    if (is_dir($dir) && count(scandir($dir) ?: []) === 2) {
        @rmdir($dir);
    }
}

function owasys_structure_apply_json(string $file): array
{
    $json = json_decode((string) file_get_contents($file), true);
    if (!is_array($json)) {
        throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_JSON_INVALID: ' . $file);
    }
    return $json;
}

function owasys_structure_apply_write_json(string $file, array $value): void
{
    $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($encoded) || file_put_contents($file, $encoded . "\n") === false) {
        throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_JSON_WRITE_FAILED: ' . $file);
    }
}

foreach ([
    __FILE__,
    $root . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Owasys' . DIRECTORY_SEPARATOR . 'StructureDraftApplier.php',
    $root . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Owasys' . DIRECTORY_SEPARATOR . 'StructureDraftWritePlanner.php',
] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "OWASYS_STRUCTURE_DRAFT_APPLY_REQUIRED_FILE_MISSING: {$file}\n");
        exit(1);
    }
    $output = [];
    $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, "OWASYS_STRUCTURE_DRAFT_APPLY_PARSE_ERROR: {$file}\n" . implode("\n", $output) . "\n");
        exit(1);
    }
}

owasys_structure_apply_remove_tree($smokeSiteRoot);
owasys_structure_apply_remove_database($database);

try {
    owasys_structure_apply_copy_tree($sourceSiteRoot, $smokeSiteRoot);

    $siteFile = $smokeSiteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json';
    $fsmFile = $smokeSiteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'application.fsm.json';
    $site = owasys_structure_apply_json($siteFile);
    $site['site_id'] = $smokeSiteId;
    $site['site_name'] = 'OWASYS Apply Draft Smoke Demo';
    owasys_structure_apply_write_json($siteFile, $site);
    $fsm = owasys_structure_apply_json($fsmFile);
    $fsm['site_id'] = $smokeSiteId;
    owasys_structure_apply_write_json($fsmFile, $fsm);

    $registry = RegistryRepository::forOwasysSite($owasysSiteRoot, $root, $databaseRelative);
    $registry->synchronize($seedFile);
    $entry = null;
    foreach ($registry->entries() as $candidate) {
        if (is_array($candidate) && ($candidate['id'] ?? null) === $smokeSiteId) {
            $entry = $candidate;
            break;
        }
    }
    if (!is_array($entry) || ($entry['root_path'] ?? null) !== 'sites/' . $smokeSiteId) {
        throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_REGISTRY_ENTRY_INVALID');
    }

    $inspector = ApplicationInspector::forOpusRoot($root);
    $inspection = $inspector->inspectEntry($entry);
    $draftRepository = StructureDraftRepository::forRegistry($registry);
    $draft = $draftRepository->prepareAddStateDraft($entry, $inspection, [
        'state_id' => 'support',
        'route_path' => '/support',
        'title_key' => 'state.support.title',
        'event_name' => 'open_support',
    ], 'apply-smoke');
    if (($draft['id'] ?? 0) < 1 || ($draft['disk_mutation'] ?? true) !== false) {
        throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_DRAFT_INVALID');
    }

    $applier = StructureDraftApplier::forOpusRoot($root, $registry);
    $result = $applier->applyAddStateDraft($entry, (int) $draft['id'], 'apply-smoke');
    if (($result['contract'] ?? null) !== StructureDraftApplier::RESULT_CONTRACT || ($result['status'] ?? null) !== 'applied') {
        throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_RESULT_INVALID');
    }
    if (($result['state_id'] ?? null) !== 'support' || ($result['route_path'] ?? null) !== '/support') {
        throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_RESULT_TARGET_INVALID');
    }
    $serverPlan = is_array($result['server_write_plan'] ?? null) ? $result['server_write_plan'] : [];
    if (($serverPlan['contract'] ?? null) !== StructureDraftWritePlanner::CONTRACT || ($serverPlan['status'] ?? null) !== 'ready' || ($serverPlan['disk_mutation'] ?? true) !== false) {
        throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_SERVER_PLAN_NOT_CONFIRMED');
    }
    if (count((array) ($serverPlan['files'] ?? [])) < 5) {
        throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_SERVER_PLAN_INCOMPLETE');
    }

    $supportRoot = $smokeSiteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'states' . DIRECTORY_SEPARATOR . 'support';
    $supportView = $supportRoot . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'index.php';
    $supportTemplate = $supportRoot . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'index.score';
    $supportLocal = $smokeSiteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR . 'fr.php';
    foreach ([$supportView, $supportTemplate, $supportLocal] as $expectedFile) {
        if (!is_file($expectedFile)) {
            throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_WRITTEN_FILE_MISSING: ' . $expectedFile);
        }
    }

    $routes = owasys_structure_apply_json($smokeSiteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'routes.json');
    $applicationFsm = owasys_structure_apply_json($fsmFile);
    $legacyFsm = owasys_structure_apply_json($smokeSiteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'fsm.json');
    if (!str_contains(json_encode($routes, JSON_UNESCAPED_SLASHES) ?: '', 'application/states/support/views/index.php')) {
        throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_ROUTE_NOT_WRITTEN');
    }
    if (!str_contains(json_encode($applicationFsm, JSON_UNESCAPED_SLASHES) ?: '', 'open_support') || !str_contains(json_encode($legacyFsm, JSON_UNESCAPED_SLASHES) ?: '', 'SUPPORT')) {
        throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_FSM_NOT_WRITTEN');
    }
    $messages = require $supportLocal;
    if (!is_array($messages) || ($messages['state.support.title'] ?? null) !== 'Support') {
        throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_I18N_NOT_WRITTEN');
    }

    if ($registry->eventCount('apply_structure_draft') !== 1) {
        throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_EVENT_NOT_PERSISTED');
    }
    $applyContext = $registry->runtimeValue('last_structure_apply');
    if (!is_array($applyContext) || ($applyContext['state_id'] ?? null) !== 'support' || ($applyContext['server_write_plan']['status'] ?? null) !== 'ready') {
        throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_CONTEXT_NOT_PERSISTED');
    }
    $recent = $draftRepository->recentDrafts($smokeSiteId, 1);
    if (($recent[0]['status'] ?? null) !== 'applied' || ($recent[0]['disk_mutation'] ?? false) !== true) {
        throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_DRAFT_NOT_MARKED_APPLIED');
    }

    try {
        $applier->applyAddStateDraft($entry, (int) $draft['id'], 'apply-smoke');
        throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_REAPPLY_ACCEPTED');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'OWASYS_STRUCTURE_DRAFT_APPLY_DRAFT_STATUS_INVALID')) {
            throw $exception;
        }
    }

    $output = [];
    $code = 0;
    exec(PHP_BINARY . ' ' . escapeshellarg($root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'opus') . ' validate:site ' . escapeshellarg($smokeSiteId) . ' 2>&1', $output, $code);
    if ($code !== 0 || !in_array('OPUS_VALIDATE_SITE_OK: ' . $smokeSiteId, $output, true)) {
        throw new RuntimeException("OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_VALIDATE_SITE_FAILED\n" . implode("\n", $output));
    }

    $blockedInspection = $inspector->inspectEntry($entry);
    $blockedDraft = $draftRepository->prepareAddStateDraft($entry, $blockedInspection, [
        'state_id' => 'blockedapply',
        'route_path' => '/blockedapply',
        'title_key' => 'state.blockedapply.title',
        'event_name' => 'open_blockedapply',
    ], 'apply-smoke');
    $blockedRoot = $smokeSiteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'states' . DIRECTORY_SEPARATOR . 'blockedapply';
    if (!is_dir($blockedRoot) && !mkdir($blockedRoot, 0775, true) && !is_dir($blockedRoot)) {
        throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_BLOCKED_ROOT_CREATE_FAILED');
    }
    try {
        $applier->applyAddStateDraft($entry, (int) $blockedDraft['id'], 'apply-smoke');
        throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_BLOCKED_PLAN_ACCEPTED');
    } catch (RuntimeException $exception) {
        if (!str_contains($exception->getMessage(), 'OWASYS_STRUCTURE_DRAFT_APPLY_SERVER_PLAN_BLOCKED')) {
            throw $exception;
        }
    }
    if ($registry->eventCount('apply_structure_draft') !== 1) {
        throw new RuntimeException('OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_BLOCKED_PLAN_MUTATED_RUNTIME');
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} finally {
    owasys_structure_apply_remove_tree($smokeSiteRoot);
    owasys_structure_apply_remove_database($database);
}

if (is_dir($smokeSiteRoot)) {
    fwrite(STDERR, "OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_SITE_CLEANUP_FAILED\n");
    exit(1);
}
if (is_file($database)) {
    fwrite(STDERR, "OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_DATABASE_CLEANUP_FAILED\n");
    exit(1);
}

echo "OWASYS_STRUCTURE_DRAFT_APPLY_SMOKE_OK\n";
