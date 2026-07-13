<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$bin = $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'opus';
$tmpRoot = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'bin-fsm-transition-smoke';
$contextFile = $tmpRoot . DIRECTORY_SEPARATOR . 'owasys-context.json';

function opus_bin_fsm_transition_smoke_remove_tree(string $path): void
{
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
}

function opus_bin_fsm_transition_smoke_run(array $arguments): array
{
    $command = PHP_BINARY;
    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg($argument);
    }
    $output = [];
    $code = 0;
    exec($command . ' 2>&1', $output, $code);
    return ['code' => (int) $code, 'output' => implode("\n", $output)];
}

if (!is_file($bin)) {
    fwrite(STDERR, "OPUS_BIN_FSM_TRANSITION_BIN_MISSING\n");
    exit(1);
}

$lint = opus_bin_fsm_transition_smoke_run([$bin, 'help']);
if ($lint['code'] !== 0 || !str_contains($lint['output'], 'fsm:transition SITE_ID CURRENT_STATE EVENT')) {
    fwrite(STDERR, $lint['output'] . "\n");
    throw new RuntimeException('OPUS_BIN_FSM_TRANSITION_HELP_MARKER_MISSING');
}

opus_bin_fsm_transition_smoke_remove_tree($tmpRoot);
try {
    if (!is_dir($tmpRoot) && !mkdir($tmpRoot, 0777, true) && !is_dir($tmpRoot)) {
        throw new RuntimeException('OPUS_BIN_FSM_TRANSITION_TMP_CREATE_FAILED');
    }
    file_put_contents($contextFile, json_encode(['app_exists' => true], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    $demo = opus_bin_fsm_transition_smoke_run([$bin, 'fsm:transition', 'demo-app', 'home', 'open_articles']);
    if ($demo['code'] !== 0) {
        fwrite(STDERR, $demo['output'] . "\n");
        throw new RuntimeException('OPUS_BIN_FSM_TRANSITION_DEMO_FAILED');
    }
    $demoJson = json_decode($demo['output'], true);
    if (!is_array($demoJson) || ($demoJson['contract'] ?? null) !== 'OPUS_FSM_TRANSITION_CLI_RESULT_V1') {
        throw new RuntimeException('OPUS_BIN_FSM_TRANSITION_DEMO_JSON_INVALID');
    }
    if (($demoJson['result']['to_state'] ?? null) !== 'articles' || (($demoJson['result']['actions'][0] ?? null) !== 'render_route')) {
        throw new RuntimeException('OPUS_BIN_FSM_TRANSITION_DEMO_TRANSITION_INVALID');
    }
    if (($demoJson['mutation'] ?? null) !== false || ($demoJson['actions_dispatched'] ?? null) !== false) {
        throw new RuntimeException('OPUS_BIN_FSM_TRANSITION_MUTATION_POLICY_INVALID');
    }

    $owasys = opus_bin_fsm_transition_smoke_run([$bin, 'fsm:transition', 'owasys', 'registry', 'select_app', '@' . $contextFile]);
    if ($owasys['code'] !== 0) {
        fwrite(STDERR, $owasys['output'] . "\n");
        throw new RuntimeException('OPUS_BIN_FSM_TRANSITION_OWASYS_FAILED');
    }
    $owasysJson = json_decode($owasys['output'], true);
    if (!is_array($owasysJson) || ($owasysJson['result']['to_state'] ?? null) !== 'structure' || (($owasysJson['result']['actions'][0] ?? null) !== 'set_current_app')) {
        throw new RuntimeException('OPUS_BIN_FSM_TRANSITION_OWASYS_TRANSITION_INVALID');
    }

    $missing = opus_bin_fsm_transition_smoke_run([$bin, 'fsm:transition', 'demo-app', 'articles', 'missing_event']);
    if ($missing['code'] === 0 || !str_contains($missing['output'], 'OPUS_FSM_TRANSITION_NOT_FOUND: articles:missing_event')) {
        fwrite(STDERR, $missing['output'] . "\n");
        throw new RuntimeException('OPUS_BIN_FSM_TRANSITION_NEGATIVE_FAILED');
    }

    $badContext = opus_bin_fsm_transition_smoke_run([$bin, 'fsm:transition', 'owasys', 'registry', 'select_app', '{bad-json}']);
    if ($badContext['code'] === 0 || !str_contains($badContext['output'], 'OPUS_FSM_TRANSITION_CONTEXT_JSON_INVALID')) {
        fwrite(STDERR, $badContext['output'] . "\n");
        throw new RuntimeException('OPUS_BIN_FSM_TRANSITION_BAD_CONTEXT_NOT_REFUSED');
    }
} finally {
    opus_bin_fsm_transition_smoke_remove_tree($tmpRoot);
}

if (file_exists($tmpRoot)) {
    fwrite(STDERR, "OPUS_BIN_FSM_TRANSITION_TMP_CLEANUP_FAILED\n");
    exit(1);
}

echo "OPUS_BIN_FSM_TRANSITION_SMOKE_OK\n";
