<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\ApplicationInspector;
use Opus\Owasys\RegistryRepository;
use Opus\Owasys\StructureDraftRepository;
use Opus\Owasys\StructureDraftWritePlanner;

$root = dirname(__DIR__);
$owasysSiteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';
$sourceSiteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'demo-app';
$smokeSiteId = 'owasys-write-plan-smoke-demo';
$smokeSiteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . $smokeSiteId;
$databaseRelative = 'var/registry/owasys-structure-write-plan-smoke.sqlite';
$database = $owasysSiteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $databaseRelative);
$seedFile = $owasysSiteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'registry.seed.json';

function owasys_structure_write_plan_remove_tree(string $path): void
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

function owasys_structure_write_plan_copy_tree(string $source, string $target): void
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $destination = $target . DIRECTORY_SEPARATOR . $relative;
        if ($item->isDir()) {
            if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
                throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_SMOKE_COPY_DIR_FAILED: ' . $destination);
            }
        } else {
            $parent = dirname($destination);
            if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
                throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_SMOKE_COPY_PARENT_FAILED: ' . $parent);
            }
            if (!copy($item->getPathname(), $destination)) {
                throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_SMOKE_COPY_FILE_FAILED: ' . $destination);
            }
        }
    }
}

function owasys_structure_write_plan_remove_database(string $database): void
{
    @unlink($database);
    @unlink($database . '-shm');
    @unlink($database . '-wal');
    $dir = dirname($database);
    if (is_dir($dir) && count(scandir($dir) ?: []) === 2) {
        @rmdir($dir);
    }
}

function owasys_structure_write_plan_json(string $file): array
{
    $json = json_decode((string) file_get_contents($file), true);
    if (!is_array($json)) {
        throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_SMOKE_JSON_INVALID: ' . $file);
    }
    return $json;
}

function owasys_structure_write_plan_write_json(string $file, array $value): void
{
    $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($encoded) || file_put_contents($file, $encoded . "\n") === false) {
        throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_SMOKE_JSON_WRITE_FAILED: ' . $file);
    }
}

foreach ([__FILE__, $root . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Owasys' . DIRECTORY_SEPARATOR . 'StructureDraftWritePlanner.php'] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "OWASYS_STRUCTURE_WRITE_PLAN_REQUIRED_FILE_MISSING: {$file}\n");
        exit(1);
    }
    $output = [];
    $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, "OWASYS_STRUCTURE_WRITE_PLAN_PARSE_ERROR: {$file}\n" . implode("\n", $output) . "\n");
        exit(1);
    }
}

owasys_structure_write_plan_remove_tree($smokeSiteRoot);
owasys_structure_write_plan_remove_database($database);

try {
    owasys_structure_write_plan_copy_tree($sourceSiteRoot, $smokeSiteRoot);

    $siteFile = $smokeSiteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json';
    $fsmFile = $smokeSiteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'application.fsm.json';
    $site = owasys_structure_write_plan_json($siteFile);
    $site['site_id'] = $smokeSiteId;
    $site['site_name'] = 'OWASYS Write Plan Smoke Demo';
    owasys_structure_write_plan_write_json($siteFile, $site);
    $fsm = owasys_structure_write_plan_json($fsmFile);
    $fsm['site_id'] = $smokeSiteId;
    owasys_structure_write_plan_write_json($fsmFile, $fsm);

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
        throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_SMOKE_REGISTRY_ENTRY_INVALID');
    }

    $inspector = ApplicationInspector::forOpusRoot($root);
    $inspection = $inspector->inspectEntry($entry);
    $draftRepository = StructureDraftRepository::forRegistry($registry);
    $draft = $draftRepository->prepareAddStateDraft($entry, $inspection, [
        'state_id' => 'plancheck',
        'route_path' => '/plancheck',
        'title_key' => 'state.plancheck.title',
        'event_name' => 'open_plancheck',
    ], 'write-plan-smoke');

    $planner = StructureDraftWritePlanner::forOpusRoot($root);
    $plan = $planner->planAddStateDraft($entry, $draft);
    if (($plan['contract'] ?? null) !== StructureDraftWritePlanner::CONTRACT || ($plan['status'] ?? null) !== 'ready' || ($plan['disk_mutation'] ?? true) !== false) {
        throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_SMOKE_PLAN_INVALID');
    }
    $filesJson = json_encode($plan['files'] ?? [], JSON_UNESCAPED_SLASHES);
    if (!is_string($filesJson) || !str_contains($filesJson, 'config/routes.json') || !str_contains($filesJson, 'application/states/plancheck/views/index.php') || !str_contains($filesJson, 'application/states/plancheck/templates/index.score')) {
        throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_SMOKE_FILES_INVALID');
    }
    if (is_dir($smokeSiteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'states' . DIRECTORY_SEPARATOR . 'plancheck')) {
        throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_SMOKE_MUTATED_DISK');
    }

    $collisionRoot = $smokeSiteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'states' . DIRECTORY_SEPARATOR . 'plancheck';
    if (!mkdir($collisionRoot, 0775, true) && !is_dir($collisionRoot)) {
        throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_SMOKE_COLLISION_DIR_CREATE_FAILED');
    }
    $blockedPlan = $planner->planAddStateDraft($entry, $draft);
    if (($blockedPlan['status'] ?? null) !== 'blocked' || (int) ($blockedPlan['collision_count'] ?? 0) < 1) {
        throw new RuntimeException('OWASYS_STRUCTURE_WRITE_PLAN_SMOKE_COLLISION_NOT_DETECTED');
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} finally {
    owasys_structure_write_plan_remove_tree($smokeSiteRoot);
    owasys_structure_write_plan_remove_database($database);
}

if (is_dir($smokeSiteRoot)) {
    fwrite(STDERR, "OWASYS_STRUCTURE_WRITE_PLAN_SMOKE_SITE_CLEANUP_FAILED\n");
    exit(1);
}
if (is_file($database)) {
    fwrite(STDERR, "OWASYS_STRUCTURE_WRITE_PLAN_SMOKE_DATABASE_CLEANUP_FAILED\n");
    exit(1);
}

echo "OWASYS_STRUCTURE_WRITE_PLAN_SMOKE_OK\n";
