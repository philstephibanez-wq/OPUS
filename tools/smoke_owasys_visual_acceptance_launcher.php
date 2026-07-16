<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$launcher = (string) file_get_contents($root . '/RUN_OWASYS_VISUAL_ACCEPTANCE.cmd');
$router = (string) file_get_contents($root . '/tools/owasys_acceptance_router.php');

$launcherMarkers = [
    'OPUS_ENV=development',
    'OWASYS_ACCEPTANCE_PORT=18080',
    'php -S 127.0.0.1:',
    'sites\\owasys\\www',
    'tools\\owasys_acceptance_router.php',
    'http://127.0.0.1:%OWASYS_ACCEPTANCE_PORT%/login',
];
foreach ($launcherMarkers as $marker) {
    if (!str_contains($launcher, $marker)) {
        fwrite(STDERR, "OWASYS_VISUAL_ACCEPTANCE_LAUNCHER_MARKER_MISSING: {$marker}\n");
        exit(1);
    }
}

$routerMarkers = [
    "sites/owasys/www",
    "SCRIPT_NAME",
    "SCRIPT_FILENAME",
    "index.php",
    "return false",
];
foreach ($routerMarkers as $marker) {
    if (!str_contains($router, $marker)) {
        fwrite(STDERR, "OWASYS_VISUAL_ACCEPTANCE_ROUTER_MARKER_MISSING: {$marker}\n");
        exit(1);
    }
}

$forbiddenMarkers = [
    'git pull',
    'git push',
    'git reset',
    'composer install',
    'del /',
    'rmdir /',
];
foreach ($forbiddenMarkers as $marker) {
    if (stripos($launcher, $marker) !== false) {
        fwrite(STDERR, "OWASYS_VISUAL_ACCEPTANCE_LAUNCHER_FORBIDDEN_MARKER: {$marker}\n");
        exit(1);
    }
}

echo "OWASYS_VISUAL_ACCEPTANCE_LAUNCHER_SMOKE_OK\n";
