<?php

declare(strict_types=1);

/**
 * P112Q3E RefBook Reflection contract unit test.
 *
 * Public CLI contract test.
 * Role:
 *   Prove that Reflection owns technical signatures while RefBook attributes
 *   carry functional descriptions consumed by the snapshot builder.
 */
$root = dirname(__DIR__, 2);
$required = [
    'framework/Asap/RefBook/Attribute/AsapRefBookClass.php',
    'framework/Asap/RefBook/Attribute/AsapRefBookMethod.php',
    'framework/Asap/RefBook/Contract/RefBookInspectableInterface.php',
    'framework/Asap/RefBook/Model/RefBookMethodEntry.php',
    'framework/Asap/RefBook/Model/RefBookClassEntry.php',
    'framework/Asap/RefBook/Model/RefBookScanResult.php',
    'framework/Asap/RefBook/RefBookReflectionScanner.php',
    'framework/Asap/RefBook/RefBookContractValidator.php',
    'framework/Asap/RefBook/RefBookSnapshotBuilder.php',
];

foreach ($required as $relative) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($path)) {
        fwrite(STDERR, 'P112Q3E_UNIT_FAILED: FILE_MISSING: ' . $path . PHP_EOL);
        exit(1);
    }
    require_once $path;
}

$fixtureRoot = $root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'refbook';
$scanner = new ASAP\RefBook\RefBookReflectionScanner();
$result = $scanner->scan($fixtureRoot, 'ASAP\\Tests\\Fixtures\\RefBook');
$validator = new ASAP\RefBook\RefBookContractValidator();
$validation = $validator->validate($result);
$builder = new ASAP\RefBook\RefBookSnapshotBuilder();
$snapshot = $builder->build($result, $fixtureRoot);

assertEquals(1, $snapshot['summary']['classes'], 'Expected one fixture class.');
assertEquals(2, $snapshot['summary']['public_methods'], 'Expected two fixture public methods including static domain provider.');
assertEquals(0, $snapshot['summary']['class_metadata_missing'], 'Fixture class metadata must be present.');
assertEquals(1, $snapshot['summary']['method_metadata_missing'], 'Static interface domain provider intentionally has no method metadata.');
assertEquals(1, $validation['summary']['violations'], 'Exactly one fixture method metadata violation is expected.');

$class = $snapshot['classes'][0];
assertEquals('ASAP\\Tests\\Fixtures\\RefBook\\P112Q3ERefBookFixtureService', $class['name'], 'Unexpected fixture class name.');
assertEquals(true, $class['implements_refbook_inspectable'], 'Fixture must implement RefBookInspectableInterface.');
assertEquals('RefBookFixture', $class['metadata']['domain'], 'Class functional domain not extracted.');

$buildLabel = findMethod($class['methods'], 'buildLabel');
assertEquals('string', $buildLabel['parameters'][0]['type'], 'First parameter type must come from Reflection.');
assertEquals('string', $buildLabel['parameters'][1]['type'], 'Second parameter type must come from Reflection.');
assertEquals('fr', $buildLabel['parameters'][1]['default'], 'Default value must come from Reflection.');
assertEquals('string', $buildLabel['return_type'], 'Return type must come from Reflection.');
assertEquals('Build a display label from an identifier and locale', $buildLabel['metadata']['role'], 'Method functional role not extracted.');
assertEquals('P112Q3E', $buildLabel['metadata']['introduced_in'], 'Method delivery marker not extracted.');
assertEquals('asap-refbook-snapshot/v1', $snapshot['schema_version'], 'Snapshot schema version mismatch.');

echo 'P112Q3E_REFBOOK_REFLECTION_CONTRACT_UNIT_OK' . PHP_EOL;
exit(0);

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertEquals($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, 'P112Q3E_UNIT_FAILED: ' . $message . PHP_EOL);
        fwrite(STDERR, 'Expected=' . var_export($expected, true) . ' Actual=' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

/**
 * @param array<int,array<string,mixed>> $methods
 * @return array<string,mixed>
 */
function findMethod(array $methods, string $name): array
{
    foreach ($methods as $method) {
        if ($method['name'] === $name) {
            return $method;
        }
    }
    fwrite(STDERR, 'P112Q3E_UNIT_FAILED: METHOD_MISSING: ' . $name . PHP_EOL);
    exit(1);
}
