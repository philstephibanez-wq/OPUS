<?php
declare(strict_types=1);

use Opus\Api\ApiDispatcher;
use Opus\Api\ApiEndpointInterface;
use Opus\Api\ApiErrorResponseFactory;
use Opus\Api\ApiRoute;
use Opus\Api\ApiRouteRegistry;
use Opus\Application\ApplicationDefinition;
use Opus\Fsm\Runtime\FsmRuntimeConfigLoader;
use Opus\Http\Request;
use Opus\Http\Response;
use Opus\Profiler\Profiler;
use Opus\Security\Access\ConfigAclPolicy;
use Opus\Security\Fsm\ConfigFsmGuard;
use Opus\Security\Identity\IdentityContextInterface;
use Opus\Security\Sso\DevHeaderSsoAuthenticator;

$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';

echo "P7_API_REST_SSO_SECURITY_CORE_SMOKE\n";

final class P7ApiRestSsoPublicEndpoint implements ApiEndpointInterface
{
    public function handle(ApiRoute $route, ApplicationDefinition $application, Request $request, IdentityContextInterface $identity, array $context = []): Response
    {
        return Response::json([
            'ok' => true,
            'route_id' => $route->id,
            'anonymous' => $identity->isAnonymous(),
            'application' => $application->slug,
        ]);
    }
}

final class P7ApiRestSsoSecureEndpoint implements ApiEndpointInterface
{
    public function handle(ApiRoute $route, ApplicationDefinition $application, Request $request, IdentityContextInterface $identity, array $context = []): Response
    {
        return Response::json([
            'ok' => true,
            'route_id' => $route->id,
            'subject' => $identity->subject(),
            'roles' => $identity->roles(),
            'scopes' => $identity->scopes(),
            'context_has_acl' => isset($context['acl_policies']),
        ]);
    }
}

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

$request = static function (string $method, string $path, array $headers = []): Request {
    $_SERVER = [
        'HTTP_HOST' => '127.0.0.1',
        'REQUEST_METHOD' => strtoupper($method),
        'REQUEST_URI' => '/' . ltrim($path, '/'),
        'SCRIPT_NAME' => '/index.php',
    ];

    foreach ($headers as $name => $value) {
        $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = (string) $value;
    }

    return Request::fromGlobals(dirname(__DIR__, 2));
};

$dispatch = static function (ApiDispatcher $dispatcher, ApplicationDefinition $application, string $method, string $path, array $headers = []) use ($request): Response {
    $req = $request($method, $path, $headers);
    return $dispatcher->dispatch($application, $req->segments, $req);
};

$expect = static function (string $check, Response $response, int $expectedStatus) use ($fail, $responseParts): array {
    [$status, $payload] = $responseParts($response);
    if ($status !== $expectedStatus) {
        $fail($check, 'status=' . $status);
    }
    echo $check . "=OK\n";
    return $payload;
};

$temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'opus_p7_api_rest_sso_' . bin2hex(random_bytes(4));

try {
    $config = $temp . DIRECTORY_SEPARATOR . 'config';

    $writeJson($config . '/api/routes.json', [
        'contract' => 'OPUS_API_ROUTE_REGISTRY_V1',
        'routes' => [
            ['id' => 'public.ping', 'method' => 'GET', 'path' => 'api/public/ping', 'endpoint' => 'P7ApiRestSsoPublicEndpoint', 'acl_policy' => 'api.public'],
            ['id' => 'secure.me', 'method' => 'GET', 'path' => 'api/secure/me', 'endpoint' => 'P7ApiRestSsoSecureEndpoint', 'acl_policy' => 'api.secure.read'],
            ['id' => 'workflow.allowed', 'method' => 'POST', 'path' => 'api/workflow/allowed', 'endpoint' => 'P7ApiRestSsoSecureEndpoint', 'acl_policy' => 'api.secure.read', 'fsm_flow' => 'api_flow', 'fsm_signal' => 'allowed_signal'],
            ['id' => 'workflow.denied', 'method' => 'POST', 'path' => 'api/workflow/denied', 'endpoint' => 'P7ApiRestSsoSecureEndpoint', 'acl_policy' => 'api.secure.read', 'fsm_flow' => 'api_flow', 'fsm_signal' => 'missing_signal'],
        ],
    ]);

    $writeJson($config . '/security/sso.json', [
        'contract' => 'OPUS_SSO_CONFIG_V1',
        'adapter' => 'dev_header',
        'anonymous_subject' => 'anonymous',
        'headers' => ['subject' => 'X-OPUS-USER', 'roles' => 'X-OPUS-ROLES', 'scopes' => 'X-OPUS-SCOPES'],
    ]);

    $writeJson($config . '/security/acl.json', [
        'contract' => 'OPUS_ACL_POLICY_REGISTRY_V1',
        'engine' => 'hierarchical_acl',
        'policies' => [
            'api.public' => ['access' => 'public'],
            'api.secure.read' => ['access' => 'role_or_scope', 'roles' => [], 'scopes' => ['api:read']],
            'api.admin' => ['access' => 'role_or_scope', 'roles' => ['admin'], 'scopes' => []],
        ],
    ]);

    $writeJson($config . '/fsm/api_flow.json', [
        'schema' => 'OPUS_FSM_RUNTIME_V1',
        'id' => 'api_flow',
        'transitions' => [['from' => 'idle', 'signal' => 'allowed_signal', 'to' => 'done']],
    ]);

    $application = new ApplicationDefinition($temp, ['slug' => 'p7-api-smoke', 'name' => 'P7 API Smoke', 'default_lang' => 'en', 'languages' => ['en']]);
    $profiler = new Profiler($temp . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'profiler');
    $profiler->start('p7_api_rest_sso_security_core');

    $dispatcher = new ApiDispatcher(
        ApiRouteRegistry::fromFile($config . '/api/routes.json'),
        DevHeaderSsoAuthenticator::fromFile($config . '/security/sso.json'),
        ConfigAclPolicy::fromFile($config . '/security/acl.json'),
        new ConfigFsmGuard(new FsmRuntimeConfigLoader($config . '/fsm')),
        $profiler,
        new ApiErrorResponseFactory(),
        $temp
    );

    $payload = $expect('CHECK_PUBLIC_ENDPOINT_ANONYMOUS', $dispatch($dispatcher, $application, 'GET', '/api/public/ping'), 200);
    if (($payload['ok'] ?? null) !== true || ($payload['anonymous'] ?? null) !== true) { $fail('CHECK_PUBLIC_ENDPOINT_ANONYMOUS_PAYLOAD'); }

    $payload = $expect('CHECK_SECURE_ENDPOINT_ANONYMOUS_DENIED', $dispatch($dispatcher, $application, 'GET', '/api/secure/me'), 401);
    if (($payload['error']['code'] ?? '') !== 'OPUS_AUTH_REQUIRED') { $fail('CHECK_SECURE_ENDPOINT_ANONYMOUS_DENIED_CODE'); }

    $payload = $expect('CHECK_SECURE_ENDPOINT_SSO_GRANTED', $dispatch($dispatcher, $application, 'GET', '/api/secure/me', ['X-OPUS-USER' => 'user-123', 'X-OPUS-ROLES' => 'reader', 'X-OPUS-SCOPES' => 'api:read,profile']), 200);
    if (($payload['subject'] ?? null) !== 'user-123' || !in_array('api:read', (array) ($payload['scopes'] ?? []), true)) { $fail('CHECK_SECURE_ENDPOINT_SSO_PAYLOAD'); }

    $payload = $expect('CHECK_SECURE_ENDPOINT_SCOPE_DENIED', $dispatch($dispatcher, $application, 'GET', '/api/secure/me', ['X-OPUS-USER' => 'user-124', 'X-OPUS-ROLES' => 'reader', 'X-OPUS-SCOPES' => 'profile']), 403);
    if (($payload['error']['code'] ?? '') !== 'OPUS_API_FORBIDDEN') { $fail('CHECK_SECURE_ENDPOINT_SCOPE_DENIED_CODE'); }

    $expect('CHECK_ROUTE_NOT_FOUND', $dispatch($dispatcher, $application, 'GET', '/api/missing'), 404);

    $payload = $expect('CHECK_FSM_ALLOWED', $dispatch($dispatcher, $application, 'POST', '/api/workflow/allowed', ['X-OPUS-USER' => 'user-125', 'X-OPUS-SCOPES' => 'api:read']), 200);
    if (($payload['route_id'] ?? null) !== 'workflow.allowed') { $fail('CHECK_FSM_ALLOWED_PAYLOAD'); }

    $payload = $expect('CHECK_FSM_DENIED', $dispatch($dispatcher, $application, 'POST', '/api/workflow/denied', ['X-OPUS-USER' => 'user-126', 'X-OPUS-SCOPES' => 'api:read']), 409);
    if (($payload['error']['code'] ?? '') !== 'OPUS_FSM_TRANSITION_DENIED') { $fail('CHECK_FSM_DENIED_CODE'); }

    $profiler->stop(['status' => 'ok']);
    echo "P7_API_REST_SSO_SECURITY_CORE_SMOKE_OK\n";
    $cleanup($temp);
    exit(0);
} catch (Throwable $exception) {
    echo "CHECK_UNEXPECTED_EXCEPTION=FAIL " . $exception->getMessage() . PHP_EOL;
    $cleanup($temp);
    exit(1);
}
