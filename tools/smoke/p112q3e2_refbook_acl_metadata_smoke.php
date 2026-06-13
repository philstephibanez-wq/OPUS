<?php

declare(strict_types=1);

/**
 * P112Q3E2 ACL RefBook metadata smoke.
 *
 * Public smoke test.
 */
$root = dirname(__DIR__, 2);
$required = [
    'framework/Opus/Acl/AccessControl.php',
    'framework/Opus/Acl/AccessRule.php',
    'framework/Opus/Acl/AccessContext.php',
    'framework/Opus/Acl/AccessDecision.php',
    'framework/Opus/Acl/AccessConditionInterface.php',
    'framework/Opus/Acl/AccessControlException.php',
    'framework/Opus/Acl/RoleDefinition.php',
    'framework/Opus/Acl/ResourceDefinition.php',
    'framework/Opus/Acl/PrivilegeDefinition.php',
    'tests/Contract/RefBookAclMetadataContractTest.php',
    'tools/refbook/p112q3e2_refbook_acl_metadata_audit.php',
    'tools/refbook/run_p112q3e2_refbook_acl_metadata_strict.cmd',
    'tools/recipes/run_p112q3e2_delivery_recipe.cmd',
];
foreach ($required as $relative) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) {
        fwrite(STDERR, 'P112Q3E2_SMOKE_FAILED: FILE_MISSING: ' . $relative . PHP_EOL);
        exit(1);
    }
}

$accessControl = file_get_contents($root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Acl' . DIRECTORY_SEPARATOR . 'AccessControl.php');
if (!is_string($accessControl) || !str_contains($accessControl, '#[OpusRefBookClass(') || !str_contains($accessControl, 'RefBookInspectableInterface')) {
    fwrite(STDERR, 'P112Q3E2_SMOKE_FAILED: ACCESS_CONTROL_METADATA_MARKER_MISSING' . PHP_EOL);
    exit(1);
}

$audit = file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'refbook' . DIRECTORY_SEPARATOR . 'p112q3e2_refbook_acl_metadata_audit.php');
if (!is_string($audit) || !str_contains($audit, 'P112Q3E2_REFBOOK_ACL_METADATA_AUDIT_OK') || !str_contains($audit, 'snapshot.acl.latest.json')) {
    fwrite(STDERR, 'P112Q3E2_SMOKE_FAILED: AUDIT_MARKER_MISSING' . PHP_EOL);
    exit(1);
}

$recipe = file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'recipes' . DIRECTORY_SEPARATOR . 'opus_global_regression_recipe.php');
if (!is_string($recipe) || !str_contains($recipe, 'P112Q3E2_UNIT') || !str_contains($recipe, 'P112Q3E2_SMOKE')) {
    fwrite(STDERR, 'P112Q3E2_SMOKE_FAILED: GLOBAL_RECIPE_STEP_MISSING' . PHP_EOL);
    exit(1);
}

echo 'P112Q3E2_REFBOOK_ACL_METADATA_SMOKE_OK' . PHP_EOL;
exit(0);
