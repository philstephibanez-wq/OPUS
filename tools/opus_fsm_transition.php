<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Fsm\FsmSiteLoader;

$root = dirname(__DIR__);
$siteId = (string) ($argv[1] ?? '');
$currentState = (string) ($argv[2] ?? '');
$event = (string) ($argv[3] ?? '');
$contextInput = (string) ($argv[4] ?? '');

if ($siteId === '' || $currentState === '' || $event === '') {
    fwrite(STDERR, "OPUS_FSM_TRANSITION_USAGE: php tools/opus_fsm_transition.php SITE_ID CURRENT_STATE EVENT [CONTEXT_JSON|@CONTEXT_JSON_FILE]\n");
    exit(1);
}

if (!preg_match('/^[A-Za-z0-9_-]+$/', $siteId)) {
    fwrite(STDERR, "OPUS_FSM_TRANSITION_SITE_ID_INVALID: {$siteId}\n");
    exit(1);
}

$context = [];
if ($contextInput !== '') {
    if (str_starts_with($contextInput, '@')) {
        $contextFile = substr($contextInput, 1);
        if ($contextFile === '' || !is_file($contextFile)) {
            fwrite(STDERR, "OPUS_FSM_TRANSITION_CONTEXT_FILE_MISSING\n");
            exit(1);
        }
        $contextInput = (string) file_get_contents($contextFile);
    }

    $decoded = json_decode($contextInput, true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "OPUS_FSM_TRANSITION_CONTEXT_JSON_INVALID\n");
        exit(1);
    }
    $context = $decoded;
}

try {
    $processor = FsmSiteLoader::processorForSite($root, $siteId);
    $transition = $processor->transition($currentState, $event, $context);
    $output = [
        'contract' => 'OPUS_FSM_TRANSITION_CLI_RESULT_V1',
        'site_id' => $siteId,
        'current_state' => $currentState,
        'event' => $event,
        'result' => $transition,
        'mutation' => false,
        'actions_dispatched' => false,
    ];
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
