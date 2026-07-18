<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$tools = $root . '/tools';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$forbiddenFiles = [
    $tools . '/smoke_owasys_backend_first_architecture.php',
    $tools . '/smoke_owasys_legacy_renderer_removed.php',
];
foreach ($forbiddenFiles as $file) {
    if (is_file($file)) {
        $fail('OWASYS_TOOLS_OBSOLETE_FILE_PRESENT:' . basename($file));
    }
}

$forbiddenMarkers = [
    'sites/owasys/www/build-action.php',
    'sites/owasys/www/source-action.php',
    'sites/owasys/www/structure-preview.php',
    'application/default/bootstrap.php',
    'application/default/layouts/main.php',
    'application/default/http',
    'application/default/security',
    'application/application.php',
    'OWASYS_LEGACY_APPLICATION_REMOVED',
    'ow-sidebar',
    'ow-shell',
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tools, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    if ($path === __FILE__) {
        continue;
    }
    $source = file_get_contents($path);
    if (!is_string($source)) {
        $fail('OWASYS_TOOLS_UNREADABLE:' . $path);
    }
    foreach ($forbiddenMarkers as $marker) {
        if (str_contains($source, $marker)) {
            $fail('OWASYS_TOOLS_LEGACY_MARKER:' . str_replace('\\', '/', $path) . ':' . $marker);
        }
    }
}

$smokeAll = (string) file_get_contents($tools . '/smoke_all_opus.php');
foreach ([
    'tools/smoke_owasys_front_controller_boundary.php',
    'tools/smoke_owasys_default_state_layout.php',
    'tools/smoke_owasys_fsm_acl_score_navigation.php',
    'tools/smoke_owasys_score_horizontal_navigation.php',
    'tools/smoke_owasys_dev_router.php',
    'tools/smoke_owasys_no_legacy.php',
    'tools/smoke_owasys_tools_cleanup.php',
] as $required) {
    if (!str_contains($smokeAll, $required)) {
        $fail('OWASYS_TOOLS_REQUIRED_SMOKE_NOT_REGISTERED:' . $required);
    }
}

echo 'OWASYS_TOOLS_CLEANUP_SMOKE_OK' . PHP_EOL;
