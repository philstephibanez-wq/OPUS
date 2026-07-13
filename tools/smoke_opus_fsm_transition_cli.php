<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$tool = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'opus_fsm_transition.php';
$contextDirectory = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'fsm-transition-smoke';
$contextFile = $contextDirectory . DIRECTORY_SEPARATOR . 'owasys-select-app-context.json';

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

$removeTree = static function (string $path): void {
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
};

$removeTree($contextDirectory);
if (!is_dir($contextDirectory) && !mkdir($contextDirectory, 0777, true) && !is_dir($contextDirectory)) {
    throw new RuntimeException('OPUS_FSM_TRANSITION_CLI_CONTEXT_DIR_CREATE_FAILED');
}
file_put_contents($contextFile, json_encode(['app_exists' => true], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

try {
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

    $owasys = $run([$tool, 'owasys', 'registry', 'select_app', '@' . $contextFile]);
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

    $missingContextFile = $run([$tool, 'owasys', 'registry', 'select_app', '@' . $contextDirectory . DIRECTORY_SEPARATOR . 'missing.json']);
    if ($missingContextFile['code'] === 0 || !str_contains($missingContextFile['output'], 'OPUS_FSM_TRANSITION_CONTEXT_FILE_MISSING')) {
        fwrite(STDERR, $missingContextFile['output'] . "\n");
        throw new RuntimeException('OPUS_FSM_TRANSITION_CLI_MISSING_CONTEXT_FILE_NOT_REFUSED');
    }
} finally {
    $removeTree($contextDirectory);
}

if (file_exists($contextDirectory)) {
    throw new RuntimeException('OPUS_FSM_TRANSITION_CLI_CONTEXT_CLEANUP_FAILED');
}

echo "OPUS_FSM_TRANSITION_CLI_SMOKE_OK\n";
