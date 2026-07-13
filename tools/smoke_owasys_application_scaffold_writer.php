<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\ApplicationScaffoldWriter;
use Opus\Owasys\ScaffoldPlanBuilder;

/**
 * @param string $path
 */
function owasys_smoke_remove_tree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    $items = scandir($path);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        owasys_smoke_remove_tree($path . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($path);
}

$root = dirname(__DIR__);
$id = 'owasys-smoke-site-' . bin2hex(random_bytes(3));
$relativeRoot = 'sites/' . $id;
$absoluteRoot = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeRoot);

$request = [
    'id' => $id,
    'slug' => $id,
    'name' => 'OWASYS Smoke Site',
    'kind' => 'fullstack',
    'root_path' => $relativeRoot,
    'blueprint' => 'opus-site-standard',
    'default_locale' => 'fr',
    'theme' => 'starter',
    'controllers' => ['home', 'articles'],
    'routes' => [
        ['id' => 'home.index', 'path' => '/', 'controller' => 'home'],
        ['id' => 'articles.index', 'path' => '/articles', 'controller' => 'articles'],
    ],
    'datasources' => [],
    'security_profiles' => [['id' => 'admin', 'permissions' => ['*']]],
    'workflows' => [],
];

try {
    $plan = (new ScaffoldPlanBuilder())->build($request);
    $writer = new ApplicationScaffoldWriter($root);
    $dryRun = $writer->write($plan, true);
    if (($dryRun['mode'] ?? null) !== 'dry-run') {
        throw new RuntimeException('OWASYS_WRITER_DRY_RUN_SUMMARY_INVALID');
    }
    if (file_exists($absoluteRoot)) {
        throw new RuntimeException('OWASYS_WRITER_DRY_RUN_MUTATED_DISK');
    }

    $write = $writer->write($plan, false);
    if (($write['mode'] ?? null) !== 'write') {
        throw new RuntimeException('OWASYS_WRITER_WRITE_SUMMARY_INVALID');
    }

    foreach (['config/site.json', 'config/routes.json', 'config/application.fsm.json', 'config/fsm.json', 'application/default', 'application/home/views/index.php', 'www/index.php', 'www/asset/themes/starter/css/theme.css'] as $required) {
        $path = $absoluteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $required);
        if (!file_exists($path)) {
            throw new RuntimeException('OWASYS_WRITER_REQUIRED_OUTPUT_MISSING: ' . $required);
        }
    }

    $site = json_decode((string) file_get_contents($absoluteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json'), true);
    if (!is_array($site) || ($site['contract'] ?? null) !== 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL') {
        throw new RuntimeException('OWASYS_WRITER_SITE_CONTRACT_INVALID');
    }
    if (($site['application_fsm'] ?? null) !== 'config/application.fsm.json') {
        throw new RuntimeException('OWASYS_WRITER_SITE_FSM_POINTER_INVALID');
    }

    $fsm = json_decode((string) file_get_contents($absoluteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'application.fsm.json'), true);
    if (!is_array($fsm) || ($fsm['contract'] ?? null) !== 'OPUS_APPLICATION_FSM_V1' || empty($fsm['states']) || !isset($fsm['transitions'])) {
        throw new RuntimeException('OWASYS_WRITER_APPLICATION_FSM_INVALID');
    }
} finally {
    owasys_smoke_remove_tree($absoluteRoot);
}

if (file_exists($absoluteRoot)) {
    fwrite(STDERR, "OWASYS_WRITER_SMOKE_CLEANUP_FAILED\n");
    exit(1);
}

echo "OWASYS_APPLICATION_SCAFFOLD_WRITER_SMOKE_OK\n";
