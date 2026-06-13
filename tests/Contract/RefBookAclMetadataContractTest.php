<?php

declare(strict_types=1);

/**
 * P112Q3E2 ACL RefBook metadata contract test.
 *
 * Public CLI contract test.
 * Role:
 *   Prove that the second critical Opus domain (ACL) is fully covered by the
 *   Reflection + Attributes RefBook contract and still denies by default.
 */
$root = dirname(__DIR__, 2);
requireRefBookCore($root);

$aclRoot = $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Acl';
$scanner = new ASAP\RefBook\RefBookReflectionScanner();
$result = $scanner->scan($aclRoot, 'Opus\\Acl');
$validator = new ASAP\RefBook\RefBookContractValidator();
$validation = $validator->validate($result);
$summary = $validation['summary'];

assertSame(0, $summary['load_errors'], 'ACL scan must not have load errors.');
assertSame(0, $summary['class_metadata_missing'], 'Every ACL class/interface must expose OpusRefBookClass metadata.');
assertSame(0, $summary['method_metadata_missing'], 'Every ACL public method must expose OpusRefBookMethod metadata.');
assertSame(0, $summary['violations'], 'ACL RefBook contract must have zero violations.');
assertSame(11, $summary['classes'], 'Expected eleven ACL symbols in the second critical domain baseline.');
assertSame(30, $summary['public_methods'], 'Expected thirty ACL public methods after inspectable domain providers.');

$classes = [];
foreach ($result->classes() as $class) {
    $data = $class->toArray();
    $classes[$data['name']] = $data;
    assertSame('ACL', $data['metadata']['domain'] ?? null, 'Every ACL class metadata must declare domain ACL: ' . $data['name']);
    assertNonEmptyString($data['metadata']['role'] ?? '', 'Class role missing: ' . $data['name']);
    assertNonEmptyString($data['metadata']['responsibility'] ?? '', 'Class responsibility missing: ' . $data['name']);
    assertNotEmptyArray($data['metadata']['contracts'] ?? [], 'Class contracts missing: ' . $data['name']);
    assertContains('acl-runtime', $data['metadata']['diagrams'] ?? [], 'ACL class must link the generated runtime diagram: ' . $data['name']);

    foreach ($data['methods'] as $method) {
        assertNonEmptyString($method['metadata']['role'] ?? '', 'Method role missing: ' . $data['name'] . '::' . $method['name']);
        assertNonEmptyString($method['metadata']['behavior'] ?? '', 'Method behavior missing: ' . $data['name'] . '::' . $method['name']);
        assertNotEmptyArray($method['metadata']['side_effects'] ?? [], 'Method side effects contract missing: ' . $data['name'] . '::' . $method['name']);
        assertNotEmptyArray($method['metadata']['errors'] ?? [], 'Method errors contract missing: ' . $data['name'] . '::' . $method['name']);
        assertContains('tests/Contract/RefBookAclMetadataContractTest.php', $method['metadata']['test_refs'] ?? [], 'Method test reference missing: ' . $data['name'] . '::' . $method['name']);
        assertContains('acl-runtime', $method['metadata']['diagrams'] ?? [], 'Method diagram link missing: ' . $data['name'] . '::' . $method['name']);
        assertSame('P112Q3E2', $method['metadata']['introduced_in'] ?? '', 'Method delivery marker missing: ' . $data['name'] . '::' . $method['name']);
    }
}

assertHasClass($classes, 'Opus\\Acl\\AccessControl');
assertHasClass($classes, 'Opus\\Acl\\AccessDeniedException');
assertHasClass($classes, 'Opus\\Acl\\Acl');
assertHasClass($classes, 'Opus\\Acl\\AccessRule');
assertHasClass($classes, 'Opus\\Acl\\AccessContext');
assertHasClass($classes, 'Opus\\Acl\\AccessDecision');
assertHasClass($classes, 'Opus\\Acl\\AccessConditionInterface');
assertHasClass($classes, 'Opus\\Acl\\AccessControlException');
assertHasClass($classes, 'Opus\\Acl\\RoleDefinition');
assertHasClass($classes, 'Opus\\Acl\\ResourceDefinition');
assertHasClass($classes, 'Opus\\Acl\\PrivilegeDefinition');

assertSame(true, $classes['Opus\\Acl\\AccessControl']['implements_refbook_inspectable'], 'AccessControl must opt in to RefBookInspectableInterface.');
assertSame(true, $classes['Opus\\Acl\\Acl']['implements_refbook_inspectable'], 'Acl compatibility facade must opt in to RefBookInspectableInterface.');
assertSame(true, $classes['Opus\\Acl\\AccessDeniedException']['implements_refbook_inspectable'], 'AccessDeniedException must inherit RefBookInspectableInterface through AccessControlException.');
assertSame(true, $classes['Opus\\Acl\\AccessRule']['implements_refbook_inspectable'], 'AccessRule must opt in to RefBookInspectableInterface.');
assertSame(true, $classes['Opus\\Acl\\RoleDefinition']['implements_refbook_inspectable'], 'RoleDefinition must opt in to RefBookInspectableInterface.');

$accessControl = $classes['Opus\\Acl\\AccessControl'];
$decide = findMethod($accessControl['methods'], 'decide');
assertSame('Opus\\Acl\\AccessDecision', $decide['return_type'], 'AccessControl::decide return type must come from Reflection.');
assertSame('string', $decide['parameters'][0]['type'] ?? null, 'AccessControl::decide role parameter type must come from Reflection.');
assertSame('string', $decide['parameters'][1]['type'] ?? null, 'AccessControl::decide resource parameter type must come from Reflection.');
assertSame('string', $decide['parameters'][2]['type'] ?? null, 'AccessControl::decide privilege parameter type must come from Reflection.');
assertSame('?Opus\\Acl\\AccessContext', $decide['parameters'][3]['type'] ?? null, 'AccessControl::decide context parameter type must come from Reflection.');
assertContains('ACL_ACCESS_DENIED', $decide['metadata']['errors'] ?? [], 'AccessControl::decide must declare explicit deny code.');

$conditionInterface = $classes['Opus\\Acl\\AccessConditionInterface'];
$allows = findMethod($conditionInterface['methods'], 'allows');
assertSame('bool', $allows['return_type'], 'AccessConditionInterface::allows return type must come from Reflection.');
assertSame('Opus\\Acl\\AccessContext', $allows['parameters'][0]['type'] ?? null, 'AccessConditionInterface::allows context parameter type must come from Reflection.');


$aclFacade = $classes['Opus\\Acl\\Acl'];
$canView = findMethod($aclFacade['methods'], 'canView');
assertSame('bool', $canView['return_type'], 'Acl::canView return type must come from Reflection.');
assertSame('bool', $canView['parameters'][0]['type'] ?? null, 'Acl::canView allowed parameter type must come from Reflection.');
assertContains('acl-overview', $canView['metadata']['examples'] ?? [], 'Acl::canView must link ACL overview example.');
$aclRefBookDomain = findMethod($aclFacade['methods'], 'refBookDomain');
assertSame('string', $aclRefBookDomain['return_type'], 'Acl::refBookDomain return type must come from Reflection.');

$roleDefinition = $classes['Opus\\Acl\\RoleDefinition'];
$roleId = findMethod($roleDefinition['methods'], 'id');
assertSame('string', $roleId['return_type'], 'RoleDefinition::id return type must come from Reflection.');
assertContains('acl-overview', $roleId['metadata']['examples'] ?? [], 'RoleDefinition::id must link ACL overview example.');

$acl = new ASAP\Acl\AccessControl(
    [new ASAP\Acl\RoleDefinition('admin'), new ASAP\Acl\RoleDefinition('guest')],
    [new ASAP\Acl\ResourceDefinition('page.admin')],
    [new ASAP\Acl\PrivilegeDefinition('read')],
    [new ASAP\Acl\AccessRule('admin', 'page.admin', 'read', true)]
);
$allowed = $acl->decide('admin', 'page.admin', 'read');
assertSame(true, $allowed->allowed(), 'ACL runtime sanity: admin read must be allowed.');
assertSame('ACL_ALLOWED', $allowed->reason(), 'ACL runtime sanity: allowed reason mismatch.');
$denied = $acl->decide('guest', 'page.admin', 'read');
assertSame(false, $denied->allowed(), 'ACL runtime sanity: guest read must be denied by missing rule.');
assertContains('ACL_ACCESS_DENIED', $denied->reason(), 'ACL runtime sanity: denied reason mismatch.');

try {
    $acl->decide('missing', 'page.admin', 'read');
    fail('ACL runtime sanity: unknown role must fail explicitly.');
} catch (Opus\Acl\AccessControlException $exception) {
    assertContains('ACL_ROLE_UNKNOWN', $exception->getMessage(), 'ACL runtime sanity: unknown role code mismatch.');
}

$context = new ASAP\Acl\AccessContext(['tenant' => 'logandplay']);
assertSame(true, $context->has('tenant'), 'ACL context sanity: tenant key must exist.');
assertSame('logandplay', $context->get('tenant'), 'ACL context sanity: tenant value mismatch.');
try {
    $context->get('missing');
    fail('ACL runtime sanity: missing context key must fail explicitly.');
} catch (Opus\Acl\AccessControlException $exception) {
    assertContains('ACL_CONTEXT_INVALID', $exception->getMessage(), 'ACL runtime sanity: missing context key code mismatch.');
}

echo 'P112Q3E2_REFBOOK_ACL_METADATA_CONTRACT_UNIT_OK' . PHP_EOL;
exit(0);

function requireRefBookCore(string $root): void
{
    $files = [
        'framework/Opus/RefBook/Attribute/OpusRefBookClass.php',
        'framework/Opus/RefBook/Attribute/OpusRefBookMethod.php',
        'framework/Opus/RefBook/Contract/RefBookInspectableInterface.php',
        'framework/Opus/RefBook/Model/RefBookMethodEntry.php',
        'framework/Opus/RefBook/Model/RefBookClassEntry.php',
        'framework/Opus/RefBook/Model/RefBookScanResult.php',
        'framework/Opus/RefBook/RefBookReflectionScanner.php',
        'framework/Opus/RefBook/RefBookContractValidator.php',
        'framework/Opus/RefBook/RefBookSnapshotBuilder.php',
    ];
    foreach ($files as $relative) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($path)) {
            fwrite(STDERR, 'P112Q3E2_UNIT_FAILED: FILE_MISSING: ' . $path . PHP_EOL);
            exit(1);
        }
        require_once $path;
    }
}

/** @param mixed $expected @param mixed $actual */
function assertSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, 'P112Q3E2_UNIT_FAILED: ' . $message . PHP_EOL);
        fwrite(STDERR, 'Expected=' . var_export($expected, true) . ' Actual=' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assertNonEmptyString(mixed $value, string $message): void
{
    if (!is_string($value) || trim($value) === '') {
        fail($message);
    }
}

function assertNotEmptyArray(mixed $value, string $message): void
{
    if (!is_array($value) || $value === []) {
        fail($message);
    }
}

/** @param array<int|string,mixed> $haystack */
function assertContains(string $needle, array|string $haystack, string $message): void
{
    if (is_array($haystack)) {
        if (!in_array($needle, $haystack, true)) {
            fail($message);
        }
        return;
    }
    if (!str_contains($haystack, $needle)) {
        fail($message);
    }
}

/** @param array<string,array<string,mixed>> $classes */
function assertHasClass(array $classes, string $name): void
{
    if (!isset($classes[$name])) {
        fail('Expected ACL class missing from scan: ' . $name);
    }
}

/** @param array<int,array<string,mixed>> $methods */
function findMethod(array $methods, string $name): array
{
    foreach ($methods as $method) {
        if ($method['name'] === $name) {
            return $method;
        }
    }
    fail('Method missing from scan: ' . $name);
}

function fail(string $message): void
{
    fwrite(STDERR, 'P112Q3E2_UNIT_FAILED: ' . $message . PHP_EOL);
    exit(1);
}
