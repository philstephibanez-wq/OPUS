<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$routerFile = $root . '/sites/owasys/dev-router.php';
$workspaceFile = $root . '/sites/owasys/WORKSPACE.md';

$router = is_file($routerFile) ? (string) file_get_contents($routerFile) : '';
$workspace = is_file($workspaceFile) ? (string) file_get_contents($workspaceFile) : '';

$requiredRouterMarkers = [
    "__DIR__ . '/www'",
    "require \$publicRoot . '/index.php'",
    "strtolower(pathinfo(\$candidate, PATHINFO_EXTENSION)) !== 'php'",
];
foreach ($requiredRouterMarkers as $marker) {
    if (!str_contains($router, $marker)) {
        throw new RuntimeException('OWASYS_DEV_ROUTER_MARKER_MISSING:' . $marker);
    }
}

foreach (["application/application.php", "require __DIR__ . '/application/application.php'"] as $forbidden) {
    if (str_contains($router, $forbidden)) {
        throw new RuntimeException('OWASYS_DEV_ROUTER_LEGACY_BYPASS:' . $forbidden);
    }
}

$command = 'php -S 127.0.0.1:18080 -t sites/owasys/www sites/owasys/dev-router.php';
if (!str_contains($workspace, $command)) {
    throw new RuntimeException('OWASYS_DEV_ROUTER_WORKSPACE_COMMAND_MISSING');
}

$frontController = (string) file_get_contents($root . '/sites/owasys/application/default/src/Http/FrontController.php');
if (!str_contains($frontController, "\$handler = 'score-page.php'")) {
    throw new RuntimeException('OWASYS_DEV_ROUTER_SCORE_GET_ROUTE_MISSING');
}

echo 'OWASYS_DEV_ROUTER_SMOKE_OK' . PHP_EOL;
