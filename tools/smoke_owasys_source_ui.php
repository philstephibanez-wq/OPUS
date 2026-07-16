<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'sites/owasys/application/states/source/views/index.php',
    'sites/owasys/application/states/source/templates/index.score',
    'sites/owasys/www/source-action.php',
];
foreach ($required as $relative) {
    if (!is_file($root . '/' . $relative)) {
        fwrite(STDERR, 'OWASYS_SOURCE_UI_FILE_MISSING: ' . $relative . "\n");
        exit(1);
    }
}

$routes = json_decode((string) file_get_contents($root . '/sites/owasys/config/routes.json'), true);
$fsm = json_decode((string) file_get_contents($root . '/sites/owasys/config/owasys-navigation.fsm.json'), true);
if (!is_array($routes) || !is_array($fsm)) {
    fwrite(STDERR, "OWASYS_SOURCE_UI_CONFIG_INVALID\n");
    exit(1);
}
$routeFound = false;
foreach ((array) ($routes['routes'] ?? []) as $route) {
    if (is_array($route) && ($route['path'] ?? null) === '/source' && ($route['state'] ?? null) === 'source') {
        $routeFound = ($route['show_in_menu'] ?? false) === true;
    }
}
$stateFound = false;
foreach ((array) ($fsm['states'] ?? []) as $state) {
    if (is_array($state) && ($state['id'] ?? null) === 'source') {
        $stateFound = ($state['requires_auth'] ?? false) === true && ($state['requires_current_app'] ?? false) === true;
    }
}
$eventFound = false;
foreach ((array) ($fsm['events'] ?? []) as $event) {
    if (is_array($event) && ($event['id'] ?? null) === 'open_source') {
        $eventFound = true;
    }
}
if (!$routeFound || !$stateFound || !$eventFound) {
    fwrite(STDERR, "OWASYS_SOURCE_UI_STATE_FIRST_INVALID\n");
    exit(1);
}

$endpoint = (string) file_get_contents($root . '/sites/owasys/www/source-action.php');
foreach (['ApplicationFileEditor', 'RepositoryInspector', "'list'", "'read'", "'preview'", "'write'", "'git-diff'", 'OWASYS_SOURCE_AUTH_REQUIRED'] as $marker) {
    if (!str_contains($endpoint, $marker)) {
        fwrite(STDERR, 'OWASYS_SOURCE_UI_ENDPOINT_MARKER_MISSING: ' . $marker . "\n");
        exit(1);
    }
}
foreach (['shell_exec(', 'passthru(', 'system(', '$_POST[\'command\']', '$payload[\'command\']'] as $forbidden) {
    if (str_contains($endpoint, $forbidden)) {
        fwrite(STDERR, 'OWASYS_SOURCE_UI_FREE_COMMAND_FORBIDDEN: ' . $forbidden . "\n");
        exit(1);
    }
}

echo "OWASYS_SOURCE_UI_SMOKE_OK\n";
