<?php

declare(strict_types=1);

/**
 * PUBLIC SMOKE SCRIPT
 *
 * Role:
 *   Validate P112Q3B SecureDispatchGate baseline after extraction in the ASAP root.
 *
 * Responsibility:
 *   Verify the patched files, route security metadata hydration and route-aware
 *   FSM/ACL gate execution with explicit assertions.
 *
 * Reads:
 *   - framework/Asap/Application/Application.php
 *   - framework/Asap/Routing/Router.php
 *   - framework/Asap/Routing/RouteMatch.php
 *   - framework/Asap/Security/SecureDispatchGate.php
 *
 * Writes:
 *   - temporary XML files under the system temporary directory, removed before exit.
 *
 * Contract:
 *   No silent success. Every missing file, missing marker or failed runtime assertion
 *   aborts with a clear P112Q3B_* error.
 */

use ASAP\Acl\AccessRule;
use ASAP\Acl\PrivilegeDefinition;
use ASAP\Acl\ResourceDefinition;
use ASAP\Acl\RoleDefinition;
use ASAP\Fsm\StateDefinition;
use ASAP\Fsm\TransitionDefinition;
use ASAP\Http\Request;
use ASAP\Routing\Router;
use ASAP\Security\SecureDispatchGate;
use ASAP\Security\SiteSecurityPolicy;
use ASAP\Site\SiteDefinition;

$root = dirname(__DIR__, 2);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'ASAP\\';

    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $path = $root . '/framework/Asap/' . str_replace('\\', '/', $relative) . '.php';

        if (is_file($path)) {
            require_once $path;
        }
    }
});

function p112q3b_fail(string $code, string $detail = ''): void
{
    $message = $detail === '' ? $code : $code . ': ' . $detail;
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function p112q3b_assert(bool $condition, string $code, string $detail = ''): void
{
    if (!$condition) {
        p112q3b_fail($code, $detail);
    }
}

function p112q3b_read(string $root, string $relativePath): string
{
    $path = $root . '/' . $relativePath;

    if (!is_file($path)) {
        p112q3b_fail('P112Q3B_FILE_MISSING', $relativePath);
    }

    $content = file_get_contents($path);

    if (!is_string($content)) {
        p112q3b_fail('P112Q3B_FILE_READ_FAILED', $relativePath);
    }

    return $content;
}

function p112q3b_contains(string $content, string $needle, string $code): void
{
    p112q3b_assert(str_contains($content, $needle), $code, $needle);
}

$application = p112q3b_read($root, 'framework/Asap/Application/Application.php');
$router = p112q3b_read($root, 'framework/Asap/Routing/Router.php');
$routeMatch = p112q3b_read($root, 'framework/Asap/Routing/RouteMatch.php');
$gate = p112q3b_read($root, 'framework/Asap/Security/SecureDispatchGate.php');
$decision = p112q3b_read($root, 'framework/Asap/Security/SecureDispatchDecision.php');
$pantherRecipe = p112q3b_read($root, 'tools/recipes/p112q3b_secure_dispatch_gate_panther_recipe.php');

p112q3b_contains($application, 'use ASAP\\Security\\SecureDispatchGate;', 'P112Q3B_APPLICATION_GATE_IMPORT_MISSING');
p112q3b_contains($application, '$match = $router->match($request, $site);', 'P112Q3B_APPLICATION_ROUTE_CANDIDATE_MISSING');
p112q3b_contains($application, '(new SecureDispatchGate())->assertAllowed($request, $securityPolicy, $match);', 'P112Q3B_APPLICATION_GATE_CALL_MISSING');
p112q3b_contains($application, ')->dispatch($request, $match)', 'P112Q3B_APPLICATION_DISPATCH_MISSING');

p112q3b_assert(
    strpos($application, '(new SecureDispatchGate())->assertAllowed($request, $securityPolicy, $match);') < strpos($application, ')->dispatch($request, $match)'),
    'P112Q3B_APPLICATION_GATE_AFTER_DISPATCH'
);

p112q3b_contains($router, 'self::readAclMetadata($routeNode)', 'P112Q3B_ROUTER_ACL_HYDRATION_MISSING');
p112q3b_contains($router, 'self::readFsmGuardMetadata($routeNode)', 'P112Q3B_ROUTER_FSM_HYDRATION_MISSING');
p112q3b_contains($routeMatch, 'public readonly ?string $acl = null', 'P112Q3B_ROUTEMATCH_ACL_MISSING');
p112q3b_contains($routeMatch, 'public readonly ?string $fsmGuard = null', 'P112Q3B_ROUTEMATCH_FSM_MISSING');
p112q3b_contains($gate, 'final class SecureDispatchGate', 'P112Q3B_GATE_CLASS_MISSING');
p112q3b_contains($gate, 'resolveAclTriplet', 'P112Q3B_GATE_ACL_RESOLVER_MISSING');
p112q3b_contains($gate, 'resolveFsmSignal', 'P112Q3B_GATE_FSM_RESOLVER_MISSING');
p112q3b_contains($decision, 'final class SecureDispatchDecision', 'P112Q3B_DECISION_CLASS_MISSING');
p112q3b_contains($pantherRecipe, 'PANTHER_CLIENT_NOT_AVAILABLE', 'P112Q3B_PANTHER_RECIPE_MARKER_MISSING');
p112q3b_contains($pantherRecipe, 'class_exists', 'P112Q3B_PANTHER_CLASS_CHECK_MISSING');
p112q3b_contains($pantherRecipe, 'ASAP_P112Q3B_PANTHER_AUTOLOAD', 'P112Q3B_PANTHER_AUTOLOAD_ENV_MISSING');

$tmpRoot = sys_get_temp_dir() . '/asap_p112q3b_' . bin2hex(random_bytes(4));

if (!mkdir($tmpRoot, 0777, true) && !is_dir($tmpRoot)) {
    p112q3b_fail('P112Q3B_TEMP_DIR_CREATE_FAILED', $tmpRoot);
}

$routesFile = $tmpRoot . '/routes.xml';
$securityFile = $tmpRoot . '/security.xml';

try {
    file_put_contents($routesFile, <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<routes>
  <route name="demo" path="/demo" methods="GET" acl="page:read" fsmGuard="ROUTE_DEMO">
    <target controllerClass="DemoController" action="show" />
  </route>
</routes>
XML);
    file_put_contents($securityFile, '<security />');

    $site = new SiteDefinition('p112q3b', '/asap', $routesFile, $securityFile);
    $match = Router::fromXml($routesFile)->match(new Request('/asap/demo', 'GET'), $site);

    p112q3b_assert($match->name === 'demo', 'P112Q3B_ROUTE_MATCH_NAME_INVALID');
    p112q3b_assert($match->acl === 'page:read', 'P112Q3B_ROUTE_MATCH_ACL_INVALID', (string) $match->acl);
    p112q3b_assert($match->fsmGuard === 'ROUTE_DEMO', 'P112Q3B_ROUTE_MATCH_FSM_INVALID', (string) $match->fsmGuard);

    $policy = new SiteSecurityPolicy(
        'START',
        'REQUEST_DEFAULT',
        [
            new StateDefinition('START'),
            new StateDefinition('ROUTE_READY'),
            new StateDefinition('POLICY_READY'),
        ],
        [
            new TransitionDefinition('START', 'ROUTE_DEMO', 'ROUTE_READY'),
            new TransitionDefinition('START', 'REQUEST_DEFAULT', 'POLICY_READY'),
        ],
        'guest',
        'site',
        'read',
        [
            new RoleDefinition('guest'),
        ],
        [
            new ResourceDefinition('site'),
            new ResourceDefinition('page'),
        ],
        [
            new PrivilegeDefinition('read'),
        ],
        [
            new AccessRule('guest', 'site', 'read', true),
            new AccessRule('guest', 'page', 'read', true),
        ]
    );

    $secureDecision = (new SecureDispatchGate())->assertAllowed(new Request('/asap/demo', 'GET'), $policy, $match);

    p112q3b_assert($secureDecision->routeName === 'demo', 'P112Q3B_DECISION_ROUTE_INVALID');
    p112q3b_assert($secureDecision->fsmSignal === 'ROUTE_DEMO', 'P112Q3B_DECISION_SIGNAL_INVALID');
    p112q3b_assert($secureDecision->fsmState === 'ROUTE_READY', 'P112Q3B_DECISION_STATE_INVALID');
    p112q3b_assert($secureDecision->resource === 'page', 'P112Q3B_DECISION_RESOURCE_INVALID');
    p112q3b_assert($secureDecision->privilege === 'read', 'P112Q3B_DECISION_PRIVILEGE_INVALID');
    p112q3b_assert($secureDecision->metadataSource === 'route', 'P112Q3B_DECISION_SOURCE_INVALID');
} finally {
    if (is_file($routesFile)) {
        unlink($routesFile);
    }

    if (is_file($securityFile)) {
        unlink($securityFile);
    }

    if (is_dir($tmpRoot)) {
        rmdir($tmpRoot);
    }
}

echo 'P112Q3B_SECURE_DISPATCH_GATE_SMOKE_OK' . PHP_EOL;
