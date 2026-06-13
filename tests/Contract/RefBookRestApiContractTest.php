<?php

declare(strict_types=1);

/**
 * P113D1 RefBook REST API contract unit test.
 *
 * Role:
 *   Prove that Opus exposes a read-only REST JSON boundary for OPUS_REF_BOOK,
 *   including code examples and the framework FSM Mermaid diagram.
 */
$root = dirname(__DIR__, 2);

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

$snapshot = $provider->snapshot();
assertEquals('opus-refbook-snapshot/v1', $snapshot['schema_version'] ?? null, 'Snapshot schema version mismatch.');
assertEquals('opus-refbook-rest/v1', $snapshot['api']['version'] ?? null, 'REST API version mismatch.');
assertTrue((int) ($snapshot['summary']['classes'] ?? 0) > 0, 'Snapshot must expose framework classes.');
assertTrue(count($snapshot['domains'] ?? []) > 0, 'Snapshot must expose domains.');
assertAssetExists($snapshot, 'examples', 'refbook-rest-api-client');
assertAssetExists($snapshot, 'examples', 'fsm-state-machine-runtime');
assertAssetExists($snapshot, 'diagrams', 'framework-fsm-runtime');

$health = decodeJsonResponse($api->handle(new ASAP\Http\Request('/api/refbook/health', 'GET')), 200);
assertEquals(true, $health['ok'] ?? null, 'Health endpoint must return ok=true.');

$example = decodeJsonResponse($api->handle(new ASAP\Http\Request('/api/refbook/examples/fsm-state-machine-runtime', 'GET')), 200);
assertContains('TransitionDefinition', $example['example']['content'] ?? '', 'FSM example must include TransitionDefinition usage.');

$diagram = decodeJsonResponse($api->handle(new ASAP\Http\Request('/api/refbook/diagrams/framework-fsm-runtime', 'GET')), 200);
assertContains('stateDiagram-v2', $diagram['diagram']['content'] ?? '', 'FSM diagram must be Mermaid stateDiagram-v2.');

$classPayload = decodeJsonResponse($api->handle(new ASAP\Http\Request('/api/refbook/classes/ASAP%5CHttp%5CRequest', 'GET')), 200);
assertEquals('Opus\\Http\\Request', $classPayload['class']['name'] ?? null, 'Class endpoint must expose Opus\\Http\\Request.');

$missing = decodeJsonResponse($api->handle(new ASAP\Http\Request('/api/refbook/examples/does-not-exist', 'GET')), 404);
assertEquals('OPUS_REFBOOK_REST_ASSET_NOT_FOUND', $missing['error']['code'] ?? null, 'Missing example must be explicit 404.');

$methodDenied = decodeJsonResponse($api->handle(new ASAP\Http\Request('/api/refbook/snapshot', 'POST')), 405);
assertEquals('OPUS_REFBOOK_REST_METHOD_NOT_ALLOWED', $methodDenied['error']['code'] ?? null, 'POST must be rejected.');

echo 'P113D1_REFBOOK_REST_API_CONTRACT_UNIT_OK' . PHP_EOL;
exit(0);

function decodeJsonResponse(Opus\Http\Response $response, int $expectedStatus): array
{
    assertEquals($expectedStatus, $response->status, 'Unexpected HTTP status.');
    $payload = json_decode($response->body, true);
    if (!is_array($payload)) {
        fwrite(STDERR, 'P113D1_UNIT_FAILED: RESPONSE_JSON_INVALID' . PHP_EOL);
        exit(1);
    }

    return $payload;
}

function assertAssetExists(array $snapshot, string $bucket, string $id): void
{
    foreach ($snapshot['documentation_assets'][$bucket] ?? [] as $asset) {
        if (($asset['id'] ?? null) === $id) {
            return;
        }
    }
    fwrite(STDERR, 'P113D1_UNIT_FAILED: ASSET_MISSING: ' . $bucket . '/' . $id . PHP_EOL);
    exit(1);
}

function assertContains(string $needle, string $actual, string $message): void
{
    if (!str_contains($actual, $needle)) {
        fwrite(STDERR, 'P113D1_UNIT_FAILED: ' . $message . PHP_EOL);
        exit(1);
    }
}

/** @param mixed $expected @param mixed $actual */
function assertEquals($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, 'P113D1_UNIT_FAILED: ' . $message . PHP_EOL);
        fwrite(STDERR, 'Expected=' . var_export($expected, true) . ' Actual=' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, 'P113D1_UNIT_FAILED: ' . $message . PHP_EOL);
        exit(1);
    }
}
