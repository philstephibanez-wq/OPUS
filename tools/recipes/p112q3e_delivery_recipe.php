<?php

declare(strict_types=1);

/**
 * P112Q3E delivery recipe.
 *
 * Public CLI recipe.
 * Role:
 *   Run the feature unit test, feature smoke, RefBook audit and ASAP global
 *   anti-regression recipe required by the workspace delivery gate.
 */
$root = dirname(__DIR__, 2);
$steps = [
    ['id' => 'P112Q3E_UNIT_OR_CONTRACT_TEST', 'command' => ['php', 'tests/Contract/RefBookReflectionContractTest.php']],
    ['id' => 'P112Q3E_FEATURE_SMOKE', 'command' => ['php', 'tools/smoke/p112q3e_refbook_reflection_contract_smoke.php']],
    ['id' => 'P112Q3E_REFBOOK_AUDIT', 'command' => ['php', 'tools/refbook/p112q3e_refbook_reflection_contract.php']],
    ['id' => 'ASAP_GLOBAL_REGRESSION_RECIPE', 'command' => ['php', 'tools/recipes/asap_global_regression_recipe.php']],
];
$failed = false;
foreach ($steps as $step) {
    $result = runStep($root, $step['id'], $step['command']);
    echo '[' . $result['status'] . '] ' . $result['id'] . ' ExitCode=' . (string) $result['exit_code'] . PHP_EOL;
    if ($result['output'] !== '') {
        echo $result['output'] . PHP_EOL;
    }
    if ($result['status'] !== 'OK') {
        $failed = true;
    }
}

if ($failed) {
    fwrite(STDERR, 'P112Q3E_DELIVERY_RECIPE_FAILED' . PHP_EOL);
    exit(1);
}

echo 'P112Q3E_UNIT_OR_CONTRACT_TEST_OK' . PHP_EOL;
echo 'P112Q3E_FEATURE_SMOKE_OK' . PHP_EOL;
echo 'P112Q3E_DELIVERY_RECIPE_OK' . PHP_EOL;
exit(0);

/** @param array<int,string> $command */
function runStep(string $root, string $id, array $command): array
{
    $relative = $command[1] ?? '';
    if ($relative === '' || !is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative))) {
        return [
            'id' => $id,
            'status' => 'FAILED',
            'exit_code' => 127,
            'output' => 'REQUIRED_STEP_FILE_MISSING: ' . $relative,
        ];
    }
    $cmd = implode(' ', array_map('escapeshellarg', $command));
    $descriptor = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($cmd, $descriptor, $pipes, $root);
    if (!is_resource($process)) {
        return ['id' => $id, 'status' => 'FAILED', 'exit_code' => 126, 'output' => 'PROCESS_START_FAILED'];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'id' => $id,
        'status' => $exitCode === 0 ? 'OK' : 'FAILED',
        'exit_code' => $exitCode,
        'output' => trim((is_string($stdout) ? $stdout : '') . PHP_EOL . (is_string($stderr) ? $stderr : '')),
    ];
}
