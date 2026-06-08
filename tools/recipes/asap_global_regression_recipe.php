<?php

declare(strict_types=1);

/**
 * ASAP global anti-regression recipe.
 *
 * Public CLI recipe.
 * Role:
 *   Run the stable ASAP smoke/unit commands required after each delivery to
 *   reduce regression and side-effect risk.
 *
 * Contract:
 *   - required steps cannot be silently skipped;
 *   - every step must exist, run and report its exit code;
 *   - writes observable JSON/MD/HTML reports;
 *   - fails the delivery gate when any required step fails.
 */
$root = dirname(__DIR__, 2);
$reportRoot = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'asap_global_regression_recipe';
ensureDirectory($reportRoot);

$steps = [
    ['id' => 'P112Q3B_SMOKE', 'command' => ['php', 'tools/smoke/p112q3b_secure_dispatch_gate_smoke.php']],
    ['id' => 'P112Q3B2_SMOKE', 'command' => ['php', 'tools/smoke/p112q3b2_secure_life_robotized_recipe_smoke.php']],
    ['id' => 'P112Q3B3_SMOKE', 'command' => ['php', 'tools/smoke/p112q3b3_recipe_final_status_smoke.php']],
    ['id' => 'P112Q3B4_SMOKE', 'command' => ['php', 'tools/smoke/p112q3b4_email_safe_forms_smoke.php']],
    ['id' => 'P112Q3C_SMOKE', 'command' => ['php', 'tools/smoke/p112q3c_public_api_coverage_matrix_smoke.php']],
    ['id' => 'P112Q3D_SMOKE', 'command' => ['php', 'tools/smoke/p112q3d_refbook_tag_contract_smoke.php']],
    ['id' => 'P112Q3E_UNIT', 'command' => ['php', 'tests/Contract/RefBookReflectionContractTest.php']],
    ['id' => 'P112Q3E_SMOKE', 'command' => ['php', 'tools/smoke/p112q3e_refbook_reflection_contract_smoke.php']],
];

$results = [];
$failed = false;
foreach ($steps as $step) {
    $result = runStep($root, $step['id'], $step['command']);
    $results[] = $result;
    echo '[' . $result['status'] . '] ' . $result['id'] . ' ExitCode=' . (string) $result['exit_code'] . PHP_EOL;
    if ($result['output'] !== '') {
        echo $result['output'] . PHP_EOL;
    }
    if ($result['status'] !== 'OK') {
        $failed = true;
    }
}

$summary = [
    'palier' => 'ASAP_GLOBAL_REGRESSION_RECIPE',
    'total_steps' => count($results),
    'failed_steps' => count(array_filter($results, static function (array $result): bool { return $result['status'] !== 'OK'; })),
    'status' => $failed ? 'FAILED' : 'OK',
];
$report = ['summary' => $summary, 'steps' => $results];
$timestamp = date('Ymd_His');
$base = $reportRoot . DIRECTORY_SEPARATOR . 'ASAP_GLOBAL_REGRESSION_RECIPE_' . $timestamp;
writeJson($base . '.json', $report);
writeText($base . '.md', buildMarkdown($report));
writeText($base . '.html', buildHtml($report));
writeJson($reportRoot . DIRECTORY_SEPARATOR . 'latest.json', $report);
writeText($reportRoot . DIRECTORY_SEPARATOR . 'latest.md', buildMarkdown($report));
writeText($reportRoot . DIRECTORY_SEPARATOR . 'latest.html', buildHtml($report));

if ($failed) {
    fwrite(STDERR, 'ASAP_GLOBAL_REGRESSION_RECIPE_FAILED' . PHP_EOL);
    exit(1);
}

echo 'ASAP_GLOBAL_REGRESSION_RECIPE_OK' . PHP_EOL;
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
            'command' => implode(' ', $command),
            'output' => 'REQUIRED_STEP_FILE_MISSING: ' . $relative,
        ];
    }

    $parts = [];
    foreach ($command as $part) {
        $parts[] = escapeshellarg($part);
    }
    $cmd = implode(' ', $parts);
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($cmd, $descriptor, $pipes, $root);
    if (!is_resource($process)) {
        return [
            'id' => $id,
            'status' => 'FAILED',
            'exit_code' => 126,
            'command' => $cmd,
            'output' => 'PROCESS_START_FAILED',
        ];
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    $output = trim((is_string($stdout) ? $stdout : '') . PHP_EOL . (is_string($stderr) ? $stderr : ''));

    return [
        'id' => $id,
        'status' => $exitCode === 0 ? 'OK' : 'FAILED',
        'exit_code' => $exitCode,
        'command' => $cmd,
        'output' => $output,
    ];
}

function ensureDirectory(string $path): void
{
    if (is_dir($path)) {
        return;
    }
    if (!mkdir($path, 0775, true) && !is_dir($path)) {
        fwrite(STDERR, 'ASAP_GLOBAL_REGRESSION_RECIPE_DIRECTORY_FAILED: ' . $path . PHP_EOL);
        exit(1);
    }
}

/** @param array<string,mixed> $data */
function writeJson(string $path, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        fwrite(STDERR, 'ASAP_GLOBAL_REGRESSION_RECIPE_JSON_FAILED: ' . $path . PHP_EOL);
        exit(1);
    }
    writeText($path, $json . PHP_EOL);
}

function writeText(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, 'ASAP_GLOBAL_REGRESSION_RECIPE_WRITE_FAILED: ' . $path . PHP_EOL);
        exit(1);
    }
}

/** @param array<string,mixed> $report */
function buildMarkdown(array $report): string
{
    $lines = ['# ASAP Global Regression Recipe', '', 'Status: **' . $report['summary']['status'] . '**', '', '## Steps', ''];
    foreach ($report['steps'] as $step) {
        $lines[] = '- ' . $step['status'] . ' — ' . $step['id'] . ' — ExitCode=' . (string) $step['exit_code'];
    }
    return implode(PHP_EOL, $lines) . PHP_EOL;
}

/** @param array<string,mixed> $report */
function buildHtml(array $report): string
{
    return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>ASAP Global Regression Recipe</title></head><body><pre>'
        . htmlspecialchars(buildMarkdown($report), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</pre></body></html>' . PHP_EOL;
}
