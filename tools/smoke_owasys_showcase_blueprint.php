<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\ApplicationCreator;

$root = dirname(__DIR__);
$siteId = 'owasys-showcase-smoke';
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . $siteId;

/**
 * Removes a directory tree created by this smoke test.
 */
function owasys_showcase_smoke_remove_tree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $itemPath = $item->getPathname();
        if ($item->isDir()) {
            @rmdir($itemPath);
        } else {
            @unlink($itemPath);
        }
    }

    @rmdir($path);
}

$request = [
    'id' => $siteId,
    'slug' => $siteId,
    'name' => 'OWASYS Showcase Smoke',
    'kind' => 'fullstack',
    'root_path' => 'sites/' . $siteId,
    'blueprint' => 'opus-demo-standard',
    'default_locale' => 'fr',
    'theme' => 'starter',
    'controllers' => ['home', 'articles', 'about', 'contact'],
    'routes' => [
        ['id' => 'home.index', 'path' => '/', 'controller' => 'home'],
        ['id' => 'articles.index', 'path' => '/articles', 'controller' => 'articles'],
        ['id' => 'about.index', 'path' => '/about', 'controller' => 'about'],
        ['id' => 'contact.index', 'path' => '/contact', 'controller' => 'contact'],
    ],
    'datasources' => [],
    'security_profiles' => [['id' => 'admin', 'permissions' => ['*']]],
    'workflows' => [],
];

owasys_showcase_smoke_remove_tree($siteRoot);

try {
    $creator = new ApplicationCreator($root);
    $dryRun = $creator->create($request, false, false);
    if (is_dir($siteRoot)) {
        throw new RuntimeException('OWASYS_SHOWCASE_BLUEPRINT_DRY_RUN_MUTATED_DISK');
    }
    if (($dryRun['site_root'] ?? null) !== 'sites/' . $siteId) {
        throw new RuntimeException('OWASYS_SHOWCASE_BLUEPRINT_DRY_RUN_ROOT_INVALID');
    }
    if (($dryRun['application_fsm'] ?? null) !== 'sites/' . $siteId . '/config/application.fsm.json') {
        throw new RuntimeException('OWASYS_SHOWCASE_BLUEPRINT_DRY_RUN_FSM_INVALID');
    }

    $created = $creator->create($request, true, true);
    if (($created['validation']['status'] ?? null) !== 'ok') {
        throw new RuntimeException('OWASYS_SHOWCASE_BLUEPRINT_VALIDATION_FAILED');
    }

    $requiredFiles = [
        'config/site.json',
        'config/routes.json',
        'config/application.fsm.json',
        'config/fsm.json',
        'application/home/views/index.php',
        'application/articles/views/index.php',
        'application/about/views/index.php',
        'application/contact/views/index.php',
        'www/index.php',
        'www/asset/themes/starter/css/theme.css',
    ];
    foreach ($requiredFiles as $relative) {
        if (!is_file($siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative))) {
            throw new RuntimeException('OWASYS_SHOWCASE_BLUEPRINT_REQUIRED_FILE_MISSING: ' . $relative);
        }
    }

    $fsm = json_decode((string) file_get_contents($siteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'application.fsm.json'), true);
    if (!is_array($fsm) || ($fsm['contract'] ?? null) !== 'OPUS_APPLICATION_FSM_V1' || count((array) ($fsm['states'] ?? [])) < 4 || count((array) ($fsm['transitions'] ?? [])) < 4) {
        throw new RuntimeException('OWASYS_SHOWCASE_BLUEPRINT_APPLICATION_FSM_INVALID');
    }

    $front = (string) file_get_contents($siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'index.php');
    foreach (['opus-nav', 'opus-hero', 'opus-grid', 'Page not found', 'OPUS_APPLICATION_FSM_V1', 'data-opus-state'] as $needle) {
        if (!str_contains($front, $needle)) {
            throw new RuntimeException('OWASYS_SHOWCASE_BLUEPRINT_FRONT_MARKER_MISSING: ' . $needle);
        }
    }

    $theme = (string) file_get_contents($siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'asset' . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'starter' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'theme.css');
    foreach (['.opus-top', '.opus-hero', '.opus-card', '.opus-button', '.opus-fsm-badge'] as $needle) {
        if (!str_contains($theme, $needle)) {
            throw new RuntimeException('OWASYS_SHOWCASE_BLUEPRINT_THEME_MARKER_MISSING: ' . $needle);
        }
    }

    $homeView = (string) file_get_contents($siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'home' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'index.php');
    foreach (['cards', 'actions', 'OWASYS pipeline'] as $needle) {
        if (!str_contains($homeView, $needle)) {
            throw new RuntimeException('OWASYS_SHOWCASE_BLUEPRINT_VIEW_MARKER_MISSING: ' . $needle);
        }
    }
} finally {
    owasys_showcase_smoke_remove_tree($siteRoot);
}

if (is_dir($siteRoot)) {
    fwrite(STDERR, "OWASYS_SHOWCASE_BLUEPRINT_CLEANUP_FAILED\n");
    exit(1);
}

echo "OWASYS_SHOWCASE_BLUEPRINT_SMOKE_OK\n";
