<?php

declare(strict_types=1);

/**
 * P113D1 Opus RefBook REST API robotized recipe.
 *
 * Role:
 *   Generate observable JSON/MD/HTML reports proving that Opus exposes its
 *   RefBook data through a read-only REST API with code examples and FSM schema.
 */
$root = dirname(__DIR__, 2);
$reportDir = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'p113d1';
ensureDirectory($reportDir);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'Opus\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

$provider = new ASAP\RefBook\Api\RefBookRestSnapshotProvider($root);
$assets = new ASAP\RefBook\Api\RefBookDocumentationAssetRepository($root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'refbook');
$api = new ASAP\RefBook\Api\RefBookRestApi($provider, $assets);

$checks = [];
$checks[] = checkEndpoint($api, '/api/refbook/health', 'GET', 200, 'health');
$checks[] = checkEndpoint($api, '/api/refbook/snapshot', 'GET', 200, 'snapshot');
$checks[] = checkEndpoint($api, '/api/refbook/domains', 'GET', 200, 'domains');
$checks[] = checkEndpoint($api, '/api/refbook/classes/ASAP%5CHttp%5CRequest', 'GET', 200, 'class_request');
$checks[] = checkEndpoint($api, '/api/refbook/examples/fsm-state-machine-runtime', 'GET', 200, 'fsm_example');
$checks[] = checkEndpoint($api, '/api/refbook/diagrams/framework-fsm-runtime', 'GET', 200, 'fsm_diagram');
$checks[] = checkEndpoint($api, '/api/refbook/snapshot', 'POST', 405, 'post_denied');

$failed = array_values(array_filter($checks, static fn (array $row): bool => $row['ok'] !== true));
$snapshotPayload = json_decode($checks[1]['body'], true);
if (!is_array($snapshotPayload)) {
    fail('P113D1_RECIPE_SNAPSHOT_JSON_INVALID');
}

$report = [
    'palier' => 'P113D1_OPUS_REFBOOK_REST_API',
    'generated_at' => gmdate('c'),
    'status' => $failed === [] ? 'OK' : 'FAILED',
    'checks' => $checks,
    'snapshot_summary' => $snapshotPayload['summary'] ?? [],
    'api' => $snapshotPayload['api'] ?? [],
    'documentation_assets' => $snapshotPayload['documentation_assets'] ?? [],
];

writeJson($reportDir . DIRECTORY_SEPARATOR . 'p113d1_refbook_rest_api_report.json', $report);
writeText($reportDir . DIRECTORY_SEPARATOR . 'p113d1_refbook_rest_api_report.md', markdownReport($report));
writeText($reportDir . DIRECTORY_SEPARATOR . 'p113d1_refbook_rest_api_report.html', htmlReport($report));
writeJson($reportDir . DIRECTORY_SEPARATOR . 'p113d1_refbook_snapshot.json', $snapshotPayload);

if ($failed !== []) {
    fail('P113D1_OPUS_REFBOOK_REST_API_RECIPE_FAILED');
}

echo 'P113D1_OPUS_REFBOOK_REST_API_RECIPE_OK' . PHP_EOL;
exit(0);

function checkEndpoint(Opus\RefBook\Api\RefBookRestApi $api, string $path, string $method, int $expectedStatus, string $id): array
{
    $response = $api->handle(new ASAP\Http\Request($path, $method));
    $body = $response->body;
    $json = json_decode($body, true);
    return [
        'id' => $id,
        'method' => $method,
        'path' => $path,
        'expected_status' => $expectedStatus,
        'observed_status' => $response->status,
        'ok' => $response->status === $expectedStatus && is_array($json),
        'body' => $body,
    ];
}

function ensureDirectory(string $path): void
{
    if (is_dir($path)) {
        return;
    }
    if (!mkdir($path, 0775, true) && !is_dir($path)) {
        fail('P113D1_REPORT_DIR_CREATE_FAILED: ' . $path);
    }
}

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function writeJson(string $path, array $payload): void
{
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        fail('P113D1_JSON_ENCODE_FAILED: ' . $path);
    }
    writeText($path, $json . PHP_EOL);
}

function writeText(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        fail('P113D1_REPORT_WRITE_FAILED: ' . $path);
    }
}

function markdownReport(array $report): string
{
    $lines = ['# P113D1 â€” Opus RefBook REST API', '', 'Status: **' . $report['status'] . '**', '', '## Checks', ''];
    foreach ($report['checks'] as $check) {
        $lines[] = '- ' . ($check['ok'] ? 'OK' : 'FAIL') . ' â€” ' . $check['method'] . ' ' . $check['path'] . ' â€” HTTP ' . $check['observed_status'];
    }
    $lines[] = '';
    $lines[] = '## Assets';
    $lines[] = '- Examples: ' . count($report['documentation_assets']['examples'] ?? []);
    $lines[] = '- Diagrams: ' . count($report['documentation_assets']['diagrams'] ?? []);
    $lines[] = '';

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

function htmlReport(array $report): string
{
    return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>P113D1 RefBook REST API</title>'
        . '<style>body{font-family:Segoe UI,Arial,sans-serif;background:#0b1220;color:#e5eefc;padding:24px}pre{background:#111c30;padding:16px;border-radius:12px;white-space:pre-wrap}.ok{color:#4ade80}.fail{color:#fb7185}</style>'
        . '</head><body><h1>P113D1 â€” Opus RefBook REST API</h1><p>Status: <strong class="' . ($report['status'] === 'OK' ? 'ok' : 'fail') . '">' . htmlspecialchars($report['status'], ENT_QUOTES, 'UTF-8') . '</strong></p><pre>'
        . htmlspecialchars(markdownReport($report), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</pre></body></html>' . PHP_EOL;
}
