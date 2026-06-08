<?php
/**
 * PUBLIC SCRIPT
 *
 * Role:
 *   Static audit smoke for ASAP P112Q3A secure-by-design baseline.
 *
 * Responsibility:
 *   Read selected framework files and verify that the current baseline exposes
 *   the expected FSM/ACL/security/routing anchors before P112Q3B.
 *
 * Arguments:
 *   None. Run from the ASAP repository root.
 *
 * Return:
 *   Exit code 0 when the audited baseline is coherent.
 *
 * Errors:
 *   Exit code 1 with explicit messages when required files or anchors are absent.
 *
 * Side effects:
 *   None. The script is read-only.
 *
 * Contract:
 *   This smoke does not validate runtime behavior and does not modify ASAP.
 *   It detects current secure-by-design anchors and reports known P112Q3B gaps.
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];
$warnings = [];

$files = [
    'application' => 'framework/Asap/Application/Application.php',
    'fsm_guard' => 'framework/Asap/Security/FsmGuard.php',
    'acl_guard' => 'framework/Asap/Security/AclGuard.php',
    'policy_loader' => 'framework/Asap/Security/SiteSecurityPolicyLoader.php',
    'state_machine' => 'framework/Asap/Fsm/StateMachine.php',
    'access_control' => 'framework/Asap/Acl/AccessControl.php',
    'router' => 'framework/Asap/Routing/Router.php',
    'route_definition' => 'framework/Asap/Routing/RouteDefinition.php',
    'route_match' => 'framework/Asap/Routing/RouteMatch.php',
];

$contents = [];
foreach ($files as $key => $relativePath) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($path)) {
        $errors[] = 'P112Q3A_FILE_MISSING: ' . $relativePath;
        continue;
    }

    $data = file_get_contents($path);
    if ($data === false) {
        $errors[] = 'P112Q3A_FILE_UNREADABLE: ' . $relativePath;
        continue;
    }

    $contents[$key] = $data;
}

$requires = [
    'application' => [
        'SiteResolver',
        'SiteSecurityPolicyLoader',
        'FsmGuard',
        'AclGuard',
        'Router::fromXml',
        'ControllerDispatcher',
    ],
    'fsm_guard' => [
        'new StateMachine',
        'assertAllowed',
        '$policy->requestSignal',
    ],
    'acl_guard' => [
        'new AccessControl',
        'AccessContext',
        'fsm_state',
        'ACCESS_DENIED',
    ],
    'policy_loader' => [
        'ASAP_SITE_SECURITY_FILE_MISSING',
        'ASAP_SITE_SECURITY_FSM_MISSING',
        'ASAP_SITE_SECURITY_ACL_MISSING',
        'TransitionDefinition',
        'AccessRule',
    ],
    'state_machine' => [
        'TRANSITION_NOT_ALLOWED',
        'No transition from',
        'No fallback state',
    ],
    'access_control' => [
        'No singleton',
        'No implicit allow',
        'No explicit rule',
    ],
    'router' => [
        'ASAP_ROUTE_NOT_FOUND',
        'ASAP_REQUEST_OUTSIDE_SITE_BASE_PATH',
        'RouteDefinition',
    ],
    'route_definition' => [
        'public readonly ?string $acl',
        'public readonly ?string $fsmGuard',
        'toManifestRow',
    ],
];

foreach ($requires as $key => $needles) {
    if (!isset($contents[$key])) {
        continue;
    }

    foreach ($needles as $needle) {
        if (!str_contains($contents[$key], $needle)) {
            $errors[] = 'P112Q3A_ANCHOR_MISSING: ' . $files[$key] . ' :: ' . $needle;
        }
    }
}

if (isset($contents['router']) && isset($contents['route_definition'])) {
    if (!str_contains($contents['router'], 'acl') && !str_contains($contents['router'], "['acl']")) {
        $warnings[] = 'P112Q3B_GAP_CONFIRMED: Router::fromXml does not visibly hydrate route ACL metadata yet.';
    }

    if (!str_contains($contents['router'], "fsmGuard") && !str_contains($contents['router'], "fsm_guard")) {
        $warnings[] = 'P112Q3B_GAP_CONFIRMED: Router::fromXml does not visibly hydrate route FSM guard metadata yet.';
    }
}

if (isset($contents['application'])) {
    $dispatchPos = strpos($contents['application'], '->dispatch(');
    $aclPos = strpos($contents['application'], 'AclGuard');
    $fsmPos = strpos($contents['application'], 'FsmGuard');

    if ($dispatchPos !== false && $aclPos !== false && $fsmPos !== false && ($dispatchPos < $aclPos || $dispatchPos < $fsmPos)) {
        $errors[] = 'P112Q3A_PIPELINE_ORDER_INVALID: Controller dispatch appears before security guards.';
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

foreach ($warnings as $warning) {
    echo $warning . PHP_EOL;
}

echo 'P112Q3A_SECURE_BY_DESIGN_STATIC_AUDIT_OK' . PHP_EOL;
