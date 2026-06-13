<?php

declare(strict_types=1);

/**
 * P112Q3E1 FSM RefBook metadata contract test.
 *
 * Public CLI contract test.
 * Role:
 *   Prove that the first critical Opus domain (FSM) is fully covered by the
 *   Reflection + Attributes RefBook contract before extending the model to the
 *   rest of the framework.
 */
$root = dirname(__DIR__, 2);
requireRefBookCore($root);

$fsmRoot = $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Fsm';
$scanner = new ASAP\RefBook\RefBookReflectionScanner();
$result = $scanner->scan($fsmRoot, 'Opus\\Fsm');
$validator = new ASAP\RefBook\RefBookContractValidator();
$validation = $validator->validate($result);
$summary = $validation['summary'];

assertSame(0, $summary['load_errors'], 'FSM scan must not have load errors.');
assertSame(0, $summary['class_metadata_missing'], 'Every FSM class/interface must expose OpusRefBookClass metadata.');
assertSame(0, $summary['method_metadata_missing'], 'Every FSM public method must expose OpusRefBookMethod metadata.');
assertSame(0, $summary['violations'], 'FSM RefBook contract must have zero violations.');
assertSame(9, $summary['classes'], 'Expected nine FSM symbols in the first critical domain baseline.');
assertSame(33, $summary['public_methods'], 'Expected thirty-three FSM public methods after inspectable domain providers.');

$classes = [];
foreach ($result->classes() as $class) {
    $data = $class->toArray();
    $classes[$data['name']] = $data;
    assertSame('FSM', $data['metadata']['domain'] ?? null, 'Every FSM class metadata must declare domain FSM: ' . $data['name']);
    assertNonEmptyString($data['metadata']['role'] ?? '', 'Class role missing: ' . $data['name']);
    assertNonEmptyString($data['metadata']['responsibility'] ?? '', 'Class responsibility missing: ' . $data['name']);
    assertNotEmptyArray($data['metadata']['contracts'] ?? [], 'Class contracts missing: ' . $data['name']);
    assertContains('fsm-runtime', $data['metadata']['diagrams'] ?? [], 'FSM class must link the generated runtime diagram: ' . $data['name']);

    foreach ($data['methods'] as $method) {
        assertNonEmptyString($method['metadata']['role'] ?? '', 'Method role missing: ' . $data['name'] . '::' . $method['name']);
        assertNonEmptyString($method['metadata']['behavior'] ?? '', 'Method behavior missing: ' . $data['name'] . '::' . $method['name']);
        assertNotEmptyArray($method['metadata']['side_effects'] ?? [], 'Method side effects contract missing: ' . $data['name'] . '::' . $method['name']);
        assertNotEmptyArray($method['metadata']['errors'] ?? [], 'Method errors contract missing: ' . $data['name'] . '::' . $method['name']);
        assertContains('tests/Contract/RefBookFsmMetadataContractTest.php', $method['metadata']['test_refs'] ?? [], 'Method test reference missing: ' . $data['name'] . '::' . $method['name']);
        assertContains('fsm-runtime', $method['metadata']['diagrams'] ?? [], 'Method diagram link missing: ' . $data['name'] . '::' . $method['name']);
        assertSame('P112Q3E1', $method['metadata']['introduced_in'] ?? '', 'Method delivery marker missing: ' . $data['name'] . '::' . $method['name']);
    }
}

assertHasClass($classes, 'Opus\\Fsm\\StateMachine');
assertHasClass($classes, 'Opus\\Fsm\\StateDefinition');
assertHasClass($classes, 'Opus\\Fsm\\TransitionDefinition');
assertHasClass($classes, 'Opus\\Fsm\\TransitionResult');
assertHasClass($classes, 'Opus\\Fsm\\StateMemory');
assertHasClass($classes, 'Opus\\Fsm\\StateMachineException');
assertHasClass($classes, 'Opus\\Fsm\\StateActionInterface');
assertHasClass($classes, 'Opus\\Fsm\\Fsm');
assertHasClass($classes, 'Opus\\Fsm\\SignalDefinition');

$stateMachine = $classes['Opus\\Fsm\\StateMachine'];
assertSame(true, $stateMachine['implements_refbook_inspectable'], 'StateMachine must opt in to RefBookInspectableInterface.');
assertSame(true, $classes['Opus\\Fsm\\Fsm']['implements_refbook_inspectable'], 'Fsm facade must opt in to RefBookInspectableInterface.');
assertSame(true, $classes['Opus\\Fsm\\SignalDefinition']['implements_refbook_inspectable'], 'SignalDefinition must opt in to RefBookInspectableInterface.');
$apply = findMethod($stateMachine['methods'], 'apply');
assertSame('Opus\\Fsm\\TransitionResult', $apply['return_type'], 'StateMachine::apply return type must come from Reflection.');
assertSame('string', $apply['parameters'][0]['type'] ?? null, 'StateMachine::apply signal parameter type must come from Reflection.');
assertContains('FSM_TRANSITION_NOT_ALLOWED', $apply['metadata']['errors'] ?? [], 'StateMachine::apply must declare transition failure code.');

$actionInterface = $classes['Opus\\Fsm\\StateActionInterface'];
$execute = findMethod($actionInterface['methods'], 'execute');
assertSame('void', $execute['return_type'], 'StateActionInterface::execute return type must come from Reflection.');
assertSame('Opus\\Fsm\\TransitionDefinition', $execute['parameters'][0]['type'] ?? null, 'Action transition parameter type must come from Reflection.');
assertSame('Opus\\Fsm\\StateMemory', $execute['parameters'][1]['type'] ?? null, 'Action memory parameter type must come from Reflection.');

$signalDefinition = $classes['Opus\\Fsm\\SignalDefinition'];
$signalId = findMethod($signalDefinition['methods'], 'id');
assertSame('string', $signalId['return_type'], 'SignalDefinition::id return type must come from Reflection.');
assertContains('fsm-definition', $signalId['metadata']['examples'] ?? [], 'SignalDefinition::id must link FSM definition example.');

$fsmFacade = $classes['Opus\\Fsm\\Fsm'];
$demoFlow = findMethod($fsmFacade['methods'], 'demoFlow');
assertSame('array', $demoFlow['return_type'], 'Fsm::demoFlow return type must come from Reflection.');
assertContains('fsm-basic-transition', $demoFlow['metadata']['examples'] ?? [], 'Fsm::demoFlow must link FSM basic transition example.');

$machine = new ASAP\Fsm\StateMachine(
    [new ASAP\Fsm\StateDefinition('A'), new ASAP\Fsm\StateDefinition('B')],
    [new ASAP\Fsm\TransitionDefinition('A', 'NEXT', 'B')],
    'A'
);
assertSame('A', $machine->currentState(), 'FSM runtime sanity: initial state mismatch.');
$resultTransition = $machine->apply('NEXT');
assertSame('B', $machine->currentState(), 'FSM runtime sanity: target state mismatch.');
assertSame('A', $resultTransition->fromState(), 'FSM runtime sanity: result from state mismatch.');
assertSame('NEXT', $resultTransition->signal(), 'FSM runtime sanity: result signal mismatch.');
assertSame('B', $resultTransition->toState(), 'FSM runtime sanity: result to state mismatch.');

try {
    $machine->apply('MISSING');
    fail('FSM runtime sanity: missing transition must fail explicitly.');
} catch (Opus\Fsm\StateMachineException $exception) {
    assertContains('FSM_TRANSITION_NOT_ALLOWED', $exception->getMessage(), 'FSM runtime sanity: missing transition code mismatch.');
}

echo 'P112Q3E1_REFBOOK_FSM_METADATA_CONTRACT_UNIT_OK' . PHP_EOL;
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
            fwrite(STDERR, 'P112Q3E1_UNIT_FAILED: FILE_MISSING: ' . $path . PHP_EOL);
            exit(1);
        }
        require_once $path;
    }
}

/** @param mixed $expected @param mixed $actual */
function assertSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, 'P112Q3E1_UNIT_FAILED: ' . $message . PHP_EOL);
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
        fail('Expected FSM class missing from scan: ' . $name);
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
    fwrite(STDERR, 'P112Q3E1_UNIT_FAILED: ' . $message . PHP_EOL);
    exit(1);
}
