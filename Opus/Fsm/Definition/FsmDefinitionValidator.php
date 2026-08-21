<?php
declare(strict_types=1);

namespace Opus\Fsm\Definition;

/** Structural validator for canonical OPUS EFSM definitions. */
final class FsmDefinitionValidator implements FsmDefinitionValidatorInterface
{
    private const ID_PATTERN = '/^[A-Za-z][A-Za-z0-9_.:-]{0,127}$/D';

    public function validate(array $definition): array
    {
        $diagnostics = [];
        $states = is_array($definition['states'] ?? null)
            ? $definition['states']
            : [];
        $signals = is_array($definition['signals'] ?? null)
            ? $definition['signals']
            : [];
        $transitions = is_array($definition['transitions'] ?? null)
            ? $definition['transitions']
            : [];

        if ($states === []) {
            $this->diagnostic($diagnostics, 'OPUS_EFSM_STATES_EMPTY', 'states', 'At least one state is required.');
        }

        $stateIds = [];
        foreach ($states as $index => $state) {
            $path = 'states[' . $index . ']';
            if (!is_array($state)) {
                $this->diagnostic($diagnostics, 'OPUS_EFSM_STATE_INVALID', $path, 'State must be an object.');
                continue;
            }
            $id = trim((string) ($state['id'] ?? ''));
            if (preg_match(self::ID_PATTERN, $id) !== 1) {
                $this->diagnostic($diagnostics, 'OPUS_EFSM_STATE_ID_INVALID', $path . '.id', 'State ID is invalid.');
                continue;
            }
            if (isset($stateIds[$id])) {
                $this->diagnostic($diagnostics, 'OPUS_EFSM_STATE_ID_DUPLICATE', $path . '.id', 'State ID must be unique.');
                continue;
            }
            $stateIds[$id] = true;
        }

        $initial = trim((string) ($definition['initial_state'] ?? ''));
        if ($initial === '' || !isset($stateIds[$initial])) {
            $this->diagnostic($diagnostics, 'OPUS_EFSM_INITIAL_STATE_INVALID', 'initial_state', 'Initial state must reference an existing state.');
        }
        $final = trim((string) ($definition['final_state'] ?? ''));
        if ($final !== '' && !isset($stateIds[$final])) {
            $this->diagnostic($diagnostics, 'OPUS_EFSM_FINAL_STATE_INVALID', 'final_state', 'Final state must reference an existing state.');
        }

        $signalIds = [];
        foreach ($signals as $index => $signal) {
            $path = 'signals[' . $index . ']';
            if (!is_array($signal)) {
                $this->diagnostic($diagnostics, 'OPUS_EFSM_SIGNAL_INVALID', $path, 'Signal must be an object.');
                continue;
            }
            $id = trim((string) ($signal['id'] ?? ''));
            if ($id !== '__any__'
                && $id !== '__default__'
                && preg_match(self::ID_PATTERN, $id) !== 1) {
                $this->diagnostic($diagnostics, 'OPUS_EFSM_SIGNAL_ID_INVALID', $path . '.id', 'Signal ID is invalid.');
                continue;
            }
            if (isset($signalIds[$id])) {
                $this->diagnostic($diagnostics, 'OPUS_EFSM_SIGNAL_ID_DUPLICATE', $path . '.id', 'Signal ID must be unique.');
                continue;
            }
            $signalIds[$id] = true;
        }

        $transitionIds = [];
        foreach ($transitions as $index => $transition) {
            $path = 'transitions[' . $index . ']';
            if (!is_array($transition)) {
                $this->diagnostic($diagnostics, 'OPUS_EFSM_TRANSITION_INVALID', $path, 'Transition must be an object.');
                continue;
            }
            $id = trim((string) ($transition['id'] ?? ''));
            if (preg_match(self::ID_PATTERN, $id) !== 1) {
                $this->diagnostic($diagnostics, 'OPUS_EFSM_TRANSITION_ID_INVALID', $path . '.id', 'Transition ID is invalid.');
            } elseif (isset($transitionIds[$id])) {
                $this->diagnostic($diagnostics, 'OPUS_EFSM_TRANSITION_ID_DUPLICATE', $path . '.id', 'Transition ID must be unique.');
            } else {
                $transitionIds[$id] = true;
            }

            $signal = trim((string) ($transition['signal'] ?? ''));
            if ($signal === '' || !isset($signalIds[$signal])) {
                $this->diagnostic($diagnostics, 'OPUS_EFSM_TRANSITION_SIGNAL_UNKNOWN', $path . '.signal', 'Transition signal must exist.');
            }

            $target = trim((string) (
                $transition['next_state']
                ?? $transition['nextState']
                ?? ''
            ));
            if ($target === '' || !isset($stateIds[$target])) {
                $this->diagnostic($diagnostics, 'OPUS_EFSM_TRANSITION_TARGET_UNKNOWN', $path . '.next_state', 'Transition target must exist.');
            }

            $scope = trim((string) ($transition['scope'] ?? ''));
            if ($scope === 'global') {
                $sources = $transition['from_states'] ?? null;
                if (!is_array($sources) || $sources === []) {
                    $this->diagnostic($diagnostics, 'OPUS_EFSM_GLOBAL_SOURCES_MISSING', $path . '.from_states', 'Global transition requires finite source states.');
                } else {
                    foreach ($sources as $sourceIndex => $source) {
                        $sourceId = is_string($source) ? trim($source) : '';
                        if ($sourceId === '' || !isset($stateIds[$sourceId])) {
                            $this->diagnostic($diagnostics, 'OPUS_EFSM_GLOBAL_SOURCE_UNKNOWN', $path . '.from_states[' . $sourceIndex . ']', 'Global source state must exist.');
                        }
                    }
                }
            } elseif ($scope === '') {
                $from = trim((string) ($transition['from'] ?? ''));
                $interrupt = trim((string) ($transition['interrupt'] ?? ''));
                $validNmi = $from === '*' && $interrupt === 'nmi';
                if (!$validNmi && ($from === '' || !isset($stateIds[$from]))) {
                    $this->diagnostic($diagnostics, 'OPUS_EFSM_TRANSITION_SOURCE_UNKNOWN', $path . '.from', 'Transition source must exist.');
                }
            } else {
                $this->diagnostic($diagnostics, 'OPUS_EFSM_TRANSITION_SCOPE_INVALID', $path . '.scope', 'Transition scope is invalid.');
            }

            $this->assertStringList($diagnostics, $transition['guards'] ?? ($transition['guard'] ?? []), $path . '.guards', 'OPUS_EFSM_GUARDS_INVALID');
            $this->assertStringList($diagnostics, $transition['actions'] ?? ($transition['action'] ?? []), $path . '.actions', 'OPUS_EFSM_ACTIONS_INVALID');

            $operations = $transition['runtime_operations'] ?? [];
            if (!is_array($operations)) {
                $this->diagnostic($diagnostics, 'OPUS_EFSM_RUNTIME_OPERATIONS_INVALID', $path . '.runtime_operations', 'Runtime operations must be a list.');
            } else {
                foreach ($operations as $operationIndex => $operation) {
                    if (!is_array($operation)) {
                        $this->diagnostic($diagnostics, 'OPUS_EFSM_RUNTIME_OPERATION_INVALID', $path . '.runtime_operations[' . $operationIndex . ']', 'Runtime operation must be an object.');
                        continue;
                    }
                    $op = trim((string) ($operation['op'] ?? ''));
                    if (!in_array($op, ['push', 'pop', 'poke', 'peek'], true)) {
                        $this->diagnostic($diagnostics, 'OPUS_EFSM_RUNTIME_OPERATION_UNKNOWN', $path . '.runtime_operations[' . $operationIndex . '].op', 'Runtime operation is not registered.');
                    }
                }
            }
        }

        return [
            'valid' => $diagnostics === [],
            'diagnostics' => $diagnostics,
        ];
    }

    public function assertValid(array $definition): void
    {
        $result = $this->validate($definition);
        if ($result['valid']) {
            return;
        }
        $first = $result['diagnostics'][0] ?? null;
        $code = is_array($first) ? (string) ($first['code'] ?? '') : '';
        throw new \RuntimeException(
            $code !== '' ? $code : 'OPUS_EFSM_DEFINITION_INVALID'
        );
    }

    /** @param list<array{code:string,path:string,message:string}> $diagnostics */
    private function diagnostic(array &$diagnostics, string $code, string $path, string $message): void
    {
        $diagnostics[] = [
            'code' => $code,
            'path' => $path,
            'message' => $message,
        ];
    }

    /** @param list<array{code:string,path:string,message:string}> $diagnostics */
    private function assertStringList(array &$diagnostics, mixed $value, string $path, string $code): void
    {
        if (is_string($value)) {
            if (trim($value) !== '') {
                return;
            }
            $this->diagnostic($diagnostics, $code, $path, 'Value must be a string list.');
            return;
        }
        if (!is_array($value)) {
            $this->diagnostic($diagnostics, $code, $path, 'Value must be a string list.');
            return;
        }
        foreach ($value as $index => $item) {
            if (!is_string($item) || trim($item) === '') {
                $this->diagnostic($diagnostics, $code, $path . '[' . $index . ']', 'List entry must be a non-empty string.');
            }
        }
    }
}