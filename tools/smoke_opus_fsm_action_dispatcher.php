<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Fsm\FsmActionDispatcher;
use Opus\Fsm\FsmProcessor;
use Opus\Fsm\FsmSiteLoader;

$root = dirname(__DIR__);

$demoFsm = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'demo-app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'application.fsm.json';
$demoProcessor = FsmProcessor::fromJsonFile($demoFsm);
$demoTransition = $demoProcessor->transition('home', 'open_articles', []);

$seen = [];
$dispatcher = new FsmActionDispatcher([
    'render_route' => static function (string $action, array $transitionResult) use (&$seen): array {
        $seen[] = $action;
        return [
            'rendered_state' => (string) ($transitionResult['to_state'] ?? ''),
            'view' => (string) (($transitionResult['target_state']['view'] ?? '') ?: ''),
        ];
    },
]);

$dispatch = $dispatcher->dispatch($demoTransition, ['request_path' => '/articles']);
if (($dispatch['contract'] ?? null) !== 'OPUS_FSM_ACTION_DISPATCH_RESULT_V1' || ($dispatch['count'] ?? null) !== 1 || $seen !== ['render_route']) {
    throw new RuntimeException('OPUS_FSM_ACTION_DISPATCHER_DEMO_DISPATCH_FAILED');
}
if (($dispatch['to_state'] ?? null) !== 'articles' || (($dispatch['executed'][0]['result']['rendered_state'] ?? null) !== 'articles')) {
    throw new RuntimeException('OPUS_FSM_ACTION_DISPATCHER_DEMO_RESULT_INVALID');
}

if (!$dispatcher->hasHandler('render_route') || $dispatcher->hasHandler('missing_action')) {
    throw new RuntimeException('OPUS_FSM_ACTION_DISPATCHER_HANDLER_LOOKUP_INVALID');
}

$missingFailed = false;
try {
    $dispatcher->dispatch([
        'contract' => 'OPUS_FSM_PROCESSOR_RESULT_V1',
        'from_state' => 'home',
        'event' => 'broken',
        'to_state' => 'articles',
        'transition_id' => 'broken',
        'actions' => ['missing_action'],
        'target_state' => ['id' => 'articles'],
    ]);
} catch (RuntimeException $exception) {
    $missingFailed = str_contains($exception->getMessage(), 'OPUS_FSM_ACTION_HANDLER_MISSING: missing_action');
}
if (!$missingFailed) {
    throw new RuntimeException('OPUS_FSM_ACTION_DISPATCHER_MISSING_HANDLER_NOT_REFUSED');
}

$invalidResultFailed = false;
try {
    $dispatcher->dispatch(['contract' => 'WRONG']);
} catch (InvalidArgumentException $exception) {
    $invalidResultFailed = str_contains($exception->getMessage(), 'OPUS_FSM_PROCESSOR_RESULT_CONTRACT_INVALID');
}
if (!$invalidResultFailed) {
    throw new RuntimeException('OPUS_FSM_ACTION_DISPATCHER_INVALID_RESULT_NOT_REFUSED');
}

$order = [];
$multiDispatcher = new FsmActionDispatcher();
$multiDispatcher->register('first_action', static function (string $action) use (&$order): string {
    $order[] = $action;
    return 'first-ok';
});
$multiDispatcher->register('second_action', static function (string $action) use (&$order): string {
    $order[] = $action;
    return 'second-ok';
});
$multiDispatch = $multiDispatcher->dispatch([
    'contract' => 'OPUS_FSM_PROCESSOR_RESULT_V1',
    'from_state' => 'a',
    'event' => 'go',
    'to_state' => 'b',
    'transition_id' => 't_a_b',
    'actions' => ['first_action', 'second_action'],
    'target_state' => ['id' => 'b'],
]);
if (($multiDispatch['count'] ?? null) !== 2 || $order !== ['first_action', 'second_action']) {
    throw new RuntimeException('OPUS_FSM_ACTION_DISPATCHER_ORDER_INVALID');
}

$emptyDispatch = $multiDispatcher->dispatch([
    'contract' => 'OPUS_FSM_PROCESSOR_RESULT_V1',
    'from_state' => 'b',
    'event' => 'noop',
    'to_state' => 'b',
    'transition_id' => 't_noop',
    'actions' => [],
    'target_state' => ['id' => 'b'],
]);
if (($emptyDispatch['count'] ?? null) !== 0 || ($emptyDispatch['executed'] ?? null) !== []) {
    throw new RuntimeException('OPUS_FSM_ACTION_DISPATCHER_EMPTY_ACTIONS_INVALID');
}

$owasysProcessor = FsmSiteLoader::forRepository($root)->processorForSite('owasys');
$owasysTransition = $owasysProcessor->transition('registry', 'select_app', ['app_exists' => true]);
$owasysDispatcher = new FsmActionDispatcher([
    'set_current_app' => static fn (string $action, array $transitionResult, array $context): array => [
        'selected_app' => (string) ($context['selected_app'] ?? 'demo-app'),
        'target_state' => (string) ($transitionResult['to_state'] ?? ''),
    ],
]);
$owasysDispatch = $owasysDispatcher->dispatch($owasysTransition, ['selected_app' => 'demo-app']);
if (($owasysDispatch['to_state'] ?? null) !== 'structure' || (($owasysDispatch['executed'][0]['action'] ?? null) !== 'set_current_app')) {
    throw new RuntimeException('OPUS_FSM_ACTION_DISPATCHER_OWASYS_ACTION_INVALID');
}

$state = $dispatcher->dispatchAndReturnState($demoTransition, ['request_path' => '/articles']);
if ($state !== 'articles') {
    throw new RuntimeException('OPUS_FSM_ACTION_DISPATCHER_RETURN_STATE_INVALID');
}

echo "OPUS_FSM_ACTION_DISPATCHER_SMOKE_OK\n";
