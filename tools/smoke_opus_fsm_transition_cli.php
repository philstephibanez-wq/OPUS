<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$tool = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'opus_fsm_transition.php';

if (!is_file($tool)) {
    fwrite(STDERR, "OPUS_FSM_TRANSITION_CLI_TOOL_MISSING\n");
    exit(1);
}

$lintOutput = [];
$lintCode = 0;
exec(PHP_BINARY . ' -l ' . escapeshellarg($tool) . ' 2>&1', $lintOutput, $lintCode);
if ($lintCode !== 0) {
    fwrite(STDERR, "OPUS_FSM_TRANSITION_CLI_TOOL_PARSE_ERROR\n" . implode("\n", $lintOutput) . "\n");
    exit(1);
}

$run = static function (array $arguments): array {
    $command = PHP_BINARY;
    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg($argument);
    }
    $output = [];
    $code = 0;
    exec($command . ' 2>&1', $output, $code);
    return ['code' => (int) $code, 'output' => implode("\n", $output)];
};

$demo = $run([$tool, 'demo-app', 'home', 'open_articles']);
if ($demo['code'] !== 0) {
    fwrite(STDERR, $demo['output'] . "\n");
    throw new RuntimeException('OPUS_FSM_TRANSITION_CLI_DEMO_FAILED');
}
$demoJson = json_decode($demo['output'], true);
if (!is_array($demoJson) || ($demoJson['contract'] ?? null) !== 'OPUS_FSM_TRANSITION_CLI_RESULT_V1') {
    throw new RuntimeException('OPUS_FSM_TRANSITION_CLI_DEMO_JSON_INVALID');
}
if (($demoJson['result']['to_state'] ?? null) !== 'articles' || (($demoJson['result']['actions'][0] ?? null) !== 'render_route')) {
    throw new RuntimeException('OPUS_FSM_TRANSITION_CLI_DEMO_TRANSITION_INVALID');
}
if (($demoJson['mutation'] ?? null) !== false || ($demoJson['actions_dispatched'] ?? null) !== false) {
    throw new RuntimeException('OPUS_FSM_TRANSITION_CLI_MUTATION_POLICY_INVALID');
}

$owasys = $run([$tool, 'owasys', 'registry', 'select_app', '{"app_exists":true}']);
if ($owasys['code'] !== 0) {
    fwrite(STDERR, $owasys['output'] . "\n");
    throw new RuntimeException('OPUS_FSM_TRANSITION_CLI_OWASYS_FAILED');
}
$owasysJson = json_decode($owasys['output'], true);
if (!is_array($owasysJson) || ($owasysJson['result']['to_state'] ?? null) !== 'structure' || (($owasysJson['result']['actions'][0] ?? null) !== 'set_current_app')) {
    throw new RuntimeException('OPUS_FSM_TRANSITION_CLI_OWASYS_TRANSITION_INVALID');
}

$missing = $run([$tool, 'demo-app', 'articles', 'missing_event']);
if ($missing['code'] === 0 || !str_contains($missing['output'], 'OPUS_FSM_TRANSITION_NOT_FOUND: articles:missing_event')) {
    fwrite(STDERR, $missing['output'] . "\n");
    throw new RuntimeException('OPUS_FSM_TRANSITION_CLI_NEGATIVE_TRANSITION_FAILED');
}

$badContext = $run([$tool, 'owasys', 'registry', 'select_app', '{bad-json}']);
if ($badContext['code'] === 0 || !str_contains($badContext['output'], 'OPUS_FSM_TRANSITION_CONTEXT_JSON_INVALID')) {
    fwrite(STDERR, $badContext['output'] . "\n");
    throw new RuntimeException('OPUS_FSM_TRANSITION_CLI_BAD_CONTEXT_NOT_REFUSED');
}

echo "OPUS_FSM_TRANSITION_CLI_SMOKE_OK\n";
