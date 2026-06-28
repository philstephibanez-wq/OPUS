<?php
declare(strict_types=1);

use Opus\Api\ApiDispatcher;
use Opus\Api\ApiErrorResponseFactory;
use Opus\Api\ApiRouteRegistry;
use Opus\Application\ApplicationDefinition;
use Opus\Fsm\Runtime\FsmRuntimeConfigLoader;
use Opus\Http\Request;
use Opus\Http\Response;
use Opus\Profiler\Profiler;
use Opus\Security\Access\ConfigAclPolicy;
use Opus\Security\Fsm\ConfigFsmGuard;
use Opus\Security\Sso\DevHeaderSsoAuthenticator;

$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';

echo "P7_LSTSAR_API_INTEGRATION_CORE_SMOKE\n";

$fail = static function (string $check, string $detail = ''): void {
    echo $check . '=FAIL' . ($detail !== '' ? ' ' . $detail : '') . PHP_EOL;
    exit(1);
};

$cleanup = static function (string $dir) use (&$cleanup): void {
    if (!is_dir($dir)) {
        return;
    }
    foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            $cleanup($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
};

$writeJson = static function (string $path, array $data): void {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
};

$responseParts = static function (Response $response): array {
    $reflection = new ReflectionClass($response);

    $status = $reflection->getProperty('status');
    $status->setAccessible(true);

    $body = $reflection->getProperty('body');
    $body->setAccessible(true);

    $decoded = json_decode((string) $body->getValue($response), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('OPUS_SMOKE_RESPONSE_JSON_INVALID');
    }

    return [(int) $status->getValue($response), $decoded];
};

$dispatch = static function (ApiDispatcher $dispatcher, ApplicationDefinition $application, string $method, string $path, array $body, array $headers = []): Response {
    $_SERVER = [
        'HTTP_HOST' => '127.0.0.1',
        'REQUEST_METHOD' => strtoupper($method),
        'REQUEST_URI' => '/' . ltrim($path, '/'),
        'SCRIPT_NAME' => '/index.php',
    ];

    foreach ($headers as $name => $value) {
        $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = (string) $value;
    }

    $request = Request::fromParts('127.0.0.1', $method, '', $path, json_encode($body, JSON_THROW_ON_ERROR));

    return $dispatcher->dispatch($application, $request->segments, $request);
};

$expect = static function (string $check, Response $response, int $expectedStatus) use ($fail, $responseParts): array {
    [$status, $payload] = $responseParts($response);
    if ($status !== $expectedStatus) {
        $fail($check, 'status=' . $status);
    }
    echo $check . "=OK\n";

    return $payload;
};

$temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'opus_p7_lstsar_api_' . bin2hex(random_bytes(4));

try {
    $config = $temp . DIRECTORY_SEPARATOR . 'config';

    $writeJson($config . '/api/routes.json', [
        'contract' => 'OPUS_API_ROUTE_REGISTRY_V1',
        'routes' => [
            [
                'id' => 'lstsar.orders.process',
                'method' => 'POST',
                'path' => 'api/lstsar/orders/process',
                'endpoint' => 'Opus\\Api\\Endpoint\\LstsarProcessEndpoint',
                'acl_policy' => 'api.lstsar.write',
                'fsm_flow' => 'lstsar_api',
                'fsm_signal' => 'process_order',
                'lstsar' => ['dataset' => 'orders', 'schema' => 'orders'],
            ],
            [
                'id' => 'lstsar.orders.restore',
                'method' => 'POST',
                'path' => 'api/lstsar/orders/restore',
                'endpoint' => 'Opus\\Api\\Endpoint\\LstsarRestoreEndpoint',
                'acl_policy' => 'api.lstsar.read',
                'fsm_flow' => 'lstsar_api',
                'fsm_signal' => 'restore_order',
                'lstsar' => ['dataset' => 'orders'],
            ],
        ],
    ]);

    $writeJson($config . '/security/sso.json', [
        'contract' => 'OPUS_SSO_CONFIG_V1',
        'adapter' => 'dev_header',
        'anonymous_subject' => 'anonymous',
        'headers' => [
            'subject' => 'X-OPUS-USER',
            'roles' => 'X-OPUS-ROLES',
            'scopes' => 'X-OPUS-SCOPES',
        ],
    ]);

    $writeJson($config . '/security/acl.json', [
        'contract' => 'OPUS_ACL_POLICY_REGISTRY_V1',
        'engine' => 'hierarchical_acl',
        'policies' => [
            'api.lstsar.write' => ['access' => 'role_or_scope', 'roles' => [], 'scopes' => ['lstsar:write']],
            'api.lstsar.read' => ['access' => 'role_or_scope', 'roles' => [], 'scopes' => ['lstsar:read']],
        ],
    ]);

    $writeJson($config . '/fsm/lstsar_api.json', [
        'schema' => 'OPUS_FSM_RUNTIME_V1',
        'id' => 'lstsar_api',
        'transitions' => [
            ['from' => 'idle', 'signal' => 'process_order', 'to' => 'stored'],
            ['from' => 'stored', 'signal' => 'restore_order', 'to' => 'restored'],
        ],
    ]);

    $writeJson($config . '/lstsar/orders.json', [
        'contract' => 'OPUS_LSTSAR_SCHEMA_V1',
        'id' => 'orders',
        'fields' => [
            'code' => [
                'source' => ['type' => 'string', 'min_length' => 2, 'max_length' => 8, 'max_bytes' => 8],
                'transform' => ['trim' => true, 'uppercase' => true, 'pad_right' => ['length' => 4, 'char' => '0']],
                'target' => ['type' => 'string', 'exact_length' => 4, 'max_bytes' => 4],
            ],
            'amount' => [
                'source' => ['type' => 'number', 'min' => 0, 'max' => 9999, 'precision' => 6, 'scale' => 3],
                'transform' => ['cast' => 'float', 'round' => 2],
                'target' => ['type' => 'number', 'min' => 0, 'max' => 9999, 'precision' => 6, 'scale' => 2],
            ],
        ],
    ]);

    $application = new ApplicationDefinition($temp, [
        'slug' => 'p7-lstsar-api-smoke',
        'name' => 'P7 LSTSAR API Smoke',
        'default_lang' => 'en',
        'languages' => ['en'],
    ]);

    $profiler = new Profiler($temp . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'profiler');
    $profiler->start('p7_lstsar_api_integration_core');

    $dispatcher = new ApiDispatcher(
        ApiRouteRegistry::fromFile($config . '/api/routes.json'),
        DevHeaderSsoAuthenticator::fromFile($config . '/security/sso.json'),
        ConfigAclPolicy::fromFile($config . '/security/acl.json'),
        new ConfigFsmGuard(new FsmRuntimeConfigLoader($config . '/fsm')),
        $profiler,
        new ApiErrorResponseFactory(),
        $temp
    );

    $payload = $expect('CHECK_LSTSAR_API_ANONYMOUS_DENIED', $dispatch($dispatcher, $application, 'POST', '/api/lstsar/orders/process', ['code' => 'ab', 'amount' => 12.345]), 401);
    if (($payload['error']['code'] ?? '') !== 'OPUS_AUTH_REQUIRED') {
        $fail('CHECK_LSTSAR_API_ANONYMOUS_DENIED_CODE');
    }

    $payload = $expect('CHECK_LSTSAR_API_SCOPE_DENIED', $dispatch($dispatcher, $application, 'POST', '/api/lstsar/orders/process', ['code' => 'ab', 'amount' => 12.345], [
        'X-OPUS-USER' => 'user-1',
        'X-OPUS-SCOPES' => 'profile',
    ]), 403);
    if (($payload['error']['code'] ?? '') !== 'OPUS_API_FORBIDDEN') {
        $fail('CHECK_LSTSAR_API_SCOPE_DENIED_CODE');
    }

    $payload = $expect('CHECK_LSTSAR_API_TARGET_REJECTED', $dispatch($dispatcher, $application, 'POST', '/api/lstsar/orders/process', ['code' => 'abcdef', 'amount' => 12.345], [
        'X-OPUS-USER' => 'writer-1',
        'X-OPUS-SCOPES' => 'lstsar:write',
    ]), 422);
    if (($payload['ok'] ?? null) !== false) {
        $fail('CHECK_LSTSAR_API_TARGET_REJECTED_PAYLOAD');
    }

    $payload = $expect('CHECK_LSTSAR_API_PROCESS_OK', $dispatch($dispatcher, $application, 'POST', '/api/lstsar/orders/process', ['code' => 'ab', 'amount' => 12.345], [
        'X-OPUS-USER' => 'writer-1',
        'X-OPUS-SCOPES' => 'lstsar:write',
    ]), 200);
    if (($payload['ok'] ?? null) !== true || ($payload['record']['code'] ?? null) !== 'AB00' || ($payload['record']['amount'] ?? null) !== 12.35) {
        $fail('CHECK_LSTSAR_API_PROCESS_OK_PAYLOAD');
    }

    $recordId = (string) ($payload['record_id'] ?? '');
    if ($recordId === '') {
        $fail('CHECK_LSTSAR_API_RECORD_ID');
    }

    $payload = $expect('CHECK_LSTSAR_API_RESTORE_OK', $dispatch($dispatcher, $application, 'POST', '/api/lstsar/orders/restore', ['record_id' => $recordId], [
        'X-OPUS-USER' => 'reader-1',
        'X-OPUS-SCOPES' => 'lstsar:read',
    ]), 200);
    if (($payload['ok'] ?? null) !== true || ($payload['record']['code'] ?? null) !== 'AB00') {
        $fail('CHECK_LSTSAR_API_RESTORE_OK_PAYLOAD');
    }

    $auditPath = $temp . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'lstsar' . DIRECTORY_SEPARATOR . 'orders' . DIRECTORY_SEPARATOR . 'audit.jsonl';
    if (!is_file($auditPath)) {
        $fail('CHECK_LSTSAR_API_AUDIT_FILE');
    }
    echo "CHECK_LSTSAR_API_AUDIT_FILE=OK\n";

    $profiler->stop(['status' => 'ok']);
    echo "P7_LSTSAR_API_INTEGRATION_CORE_SMOKE_OK\n";
    $cleanup($temp);
    exit(0);
} catch (Throwable $exception) {
    echo "CHECK_UNEXPECTED_EXCEPTION=FAIL " . $exception->getMessage() . PHP_EOL;
    $cleanup($temp);
    exit(1);
}
