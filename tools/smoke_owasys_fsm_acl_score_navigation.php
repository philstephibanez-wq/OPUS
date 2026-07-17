<?php
declare(strict_types=1);

use Opus\Fsm\FsmSiteLoader;

$root = dirname(__DIR__);
$site = $root . '/sites/owasys';
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "OWASYS_FSM_ACL_SCORE_AUTOLOAD_MISSING\n");
    exit(1);
}
require $autoload;

$fsm = FsmSiteLoader::processorForSite($root, 'owasys');
$acl = require $site . '/application/default/acl/navigation.php';
$presentation = require $site . '/application/default/navigation/menu.php';
$project = require $site . '/application/default/navigation/project.php';
$dispatch = require $site . '/application/default/navigation/dispatch.php';
$authorizeRoute = require $site . '/application/default/navigation/authorize-route.php';
$viewModel = require $site . '/application/default/navigation/view-model.php';

$contextWithApp = ['has_current_app' => true, 'current_app' => ['id' => 'owasys']];
$contextWithoutApp = ['has_current_app' => false, 'current_app' => null];

$devMenu = $project($fsm, 'home', $contextWithApp, 'dev', $presentation, $acl);
$devEvents = array_column($devMenu, 'event');
foreach (['open_home', 'open_registry', 'open_structure', 'open_data', 'open_workflows', 'open_security', 'open_source', 'open_build'] as $event) {
    if (!in_array($event, $devEvents, true)) {
        fwrite(STDERR, 'OWASYS_FSM_ACL_SCORE_DEV_EVENT_MISSING:' . $event . PHP_EOL);
        exit(1);
    }
}

$viewerMenu = $project($fsm, 'home', $contextWithApp, 'viewer', $presentation, $acl);
$viewerEvents = array_column($viewerMenu, 'event');
if ($viewerEvents !== ['open_home', 'open_registry']) {
    fwrite(STDERR, 'OWASYS_FSM_ACL_SCORE_VIEWER_MENU_INVALID:' . implode(',', $viewerEvents) . PHP_EOL);
    exit(1);
}

$devWithoutApp = $project($fsm, 'home', $contextWithoutApp, 'dev', $presentation, $acl);
$devWithoutAppEvents = array_column($devWithoutApp, 'event');
foreach (['open_structure', 'open_data', 'open_workflows', 'open_security', 'open_source', 'open_build'] as $event) {
    if (in_array($event, $devWithoutAppEvents, true)) {
        fwrite(STDERR, 'OWASYS_FSM_ACL_SCORE_GUARD_LEAK:' . $event . PHP_EOL);
        exit(1);
    }
}

$result = $dispatch($fsm, 'home', 'open_structure', $contextWithApp, 'dev', $acl);
if (($result['to_state'] ?? null) !== 'structure') {
    fwrite(STDERR, "OWASYS_FSM_ACL_SCORE_DISPATCH_TARGET_INVALID\n");
    exit(1);
}

try {
    $dispatch($fsm, 'home', 'open_structure', $contextWithApp, 'viewer', $acl);
    fwrite(STDERR, "OWASYS_FSM_ACL_SCORE_VIEWER_DISPATCH_NOT_DENIED\n");
    exit(1);
} catch (RuntimeException $exception) {
    if (!str_starts_with($exception->getMessage(), 'OWASYS_NAVIGATION_ACL_DENIED:')) {
        throw $exception;
    }
}

$authorization = $authorizeRoute($fsm, 'home', 'structure', $contextWithApp, 'dev', $acl);
if (($authorization['authorized'] ?? false) !== true || ($authorization['event'] ?? null) !== 'open_structure') {
    fwrite(STDERR, "OWASYS_FSM_ACL_SCORE_ROUTE_AUTHORIZATION_INVALID\n");
    exit(1);
}

try {
    $authorizeRoute($fsm, 'home', 'structure', $contextWithApp, 'viewer', $acl);
    fwrite(STDERR, "OWASYS_FSM_ACL_SCORE_VIEWER_ROUTE_NOT_DENIED\n");
    exit(1);
} catch (RuntimeException $exception) {
    if (!str_starts_with($exception->getMessage(), 'OWASYS_ROUTE_ACL_FSM_DENIED:')) {
        throw $exception;
    }
}

$navigationViewModel = $viewModel($devMenu, '/owasys', 'home');
if (($navigationViewModel['contract'] ?? null) !== 'OWASYS_SCORE_NAVIGATION_VIEWMODEL_V1') {
    fwrite(STDERR, "OWASYS_FSM_ACL_SCORE_VIEWMODEL_CONTRACT_INVALID\n");
    exit(1);
}

$items = is_array($navigationViewModel['items'] ?? null) ? $navigationViewModel['items'] : [];
if (count($items) !== count($devMenu)) {
    fwrite(STDERR, "OWASYS_FSM_ACL_SCORE_VIEWMODEL_COUNT_INVALID\n");
    exit(1);
}

foreach ($items as $item) {
    if (!is_array($item) || !isset($item['event'], $item['href'], $item['label_key'])) {
        fwrite(STDERR, "OWASYS_FSM_ACL_SCORE_VIEWMODEL_ITEM_INVALID\n");
        exit(1);
    }
}

echo "OWASYS_FSM_ACL_SCORE_NAVIGATION_SMOKE_OK\n";
