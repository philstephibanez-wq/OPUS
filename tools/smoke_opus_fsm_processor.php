<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Fsm\FsmProcessor;

$root = dirname(__DIR__);

$demoProcessor = FsmProcessor::fromJsonFile($root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'demo-app' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'application.fsm.json');
if ($demoProcessor->contract() !== 'OPUS_APPLICATION_FSM_V1' || $demoProcessor->initialState() !== 'home') {
    fwrite(STDERR, "OPUS_FSM_PROCESSOR_DEMO_METADATA_INVALID\n");
    exit(1);
}

$articles = $demoProcessor->transition('home', 'open_articles');
if (($articles['contract'] ?? null) !== 'OPUS_FSM_PROCESSOR_RESULT_V1' || ($articles['to_state'] ?? null) !== 'articles' || ($articles['action'] ?? null) !== 'render_route') {
    fwrite(STDERR, "OPUS_FSM_PROCESSOR_DEMO_TRANSITION_INVALID\n");
    exit(1);
}

$home = $demoProcessor->transition('articles', 'open_home');
if (($home['from_state'] ?? null) !== 'articles' || ($home['to_state'] ?? null) !== 'home') {
    fwrite(STDERR, "OPUS_FSM_PROCESSOR_DEMO_RETURN_TRANSITION_INVALID\n");
    exit(1);
}

try {
    $demoProcessor->transition('home', 'missing_event');
    fwrite(STDERR, "OPUS_FSM_PROCESSOR_MISSING_EVENT_NOT_REJECTED\n");
    exit(1);
} catch (RuntimeException $exception) {
    if ($exception->getMessage() !== 'OPUS_FSM_TRANSITION_NOT_FOUND: home:missing_event') {
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(1);
    }
}

$owasysProcessor = FsmProcessor::fromJsonFile($root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'owasys-navigation.fsm.json');
$changeApp = $owasysProcessor->transition('security', 'change_app');
if (($changeApp['from_state'] ?? null) !== 'security' || ($changeApp['to_state'] ?? null) !== 'registry') {
    fwrite(STDERR, "OPUS_FSM_PROCESSOR_WILDCARD_TRANSITION_INVALID\n");
    exit(1);
}

try {
    $owasysProcessor->transition('registry', 'select_app');
    fwrite(STDERR, "OPUS_FSM_PROCESSOR_GUARD_FAILURE_NOT_REJECTED\n");
    exit(1);
} catch (RuntimeException $exception) {
    if ($exception->getMessage() !== 'OPUS_FSM_GUARD_FAILED: app_exists') {
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(1);
    }
}

$selectApp = $owasysProcessor->transition('registry', 'select_app', ['app_exists' => true]);
if (($selectApp['to_state'] ?? null) !== 'structure' || !in_array('set_current_app', (array) ($selectApp['actions'] ?? []), true)) {
    fwrite(STDERR, "OPUS_FSM_PROCESSOR_GUARD_SUCCESS_INVALID\n");
    exit(1);
}

try {
    new FsmProcessor([
        'contract' => 'OPUS_APPLICATION_FSM_V1',
        'initial_state' => 'home',
        'states' => [
            ['id' => 'home'],
            ['id' => 'other'],
        ],
        'transitions' => [
            ['from' => 'home', 'event' => 'go', 'to' => 'other', 'guards' => ['unknown_guard']],
        ],
    ]);
    $processor = new FsmProcessor([
        'contract' => 'OPUS_APPLICATION_FSM_V1',
        'initial_state' => 'home',
        'states' => [
            ['id' => 'home'],
            ['id' => 'other'],
        ],
        'transitions' => [
            ['from' => 'home', 'event' => 'go', 'to' => 'other', 'guards' => ['unknown_guard']],
        ],
    ]);
    $processor->transition('home', 'go');
    fwrite(STDERR, "OPUS_FSM_PROCESSOR_UNKNOWN_GUARD_NOT_REJECTED\n");
    exit(1);
} catch (RuntimeException $exception) {
    if ($exception->getMessage() !== 'OPUS_FSM_GUARD_UNSUPPORTED: unknown_guard') {
        fwrite(STDERR, $exception->getMessage() . "\n");
        exit(1);
    }
}

$customProcessor = new FsmProcessor([
    'contract' => 'OPUS_APPLICATION_FSM_V1',
    'initial_state' => 'start',
    'states' => [
        ['id' => 'start'],
        ['id' => 'done'],
    ],
    'transitions' => [
        ['from' => 'start', 'event' => 'finish', 'to' => 'done', 'guards' => ['custom_ok'], 'action' => 'complete'],
    ],
], [
    'custom_ok' => static fn (string $currentState, string $event, array $transition, array $context, FsmProcessor $processor): bool => ($context['ok'] ?? null) === true && $processor->hasState('done'),
]);
$customResult = $customProcessor->transition('start', 'finish', ['ok' => true]);
if (($customResult['to_state'] ?? null) !== 'done' || ($customResult['action'] ?? null) !== 'complete') {
    fwrite(STDERR, "OPUS_FSM_PROCESSOR_CUSTOM_GUARD_INVALID\n");
    exit(1);
}

echo "OPUS_FSM_PROCESSOR_SMOKE_OK\n";
