<?php
declare(strict_types=1);

namespace Opus\Fsm\Definition;

/** Dependency-safe semantic editor for OPUS EFSM definitions. */
final class FsmDefinitionEditor implements FsmDefinitionEditorInterface
{
    private const ID_PATTERN = '/^[A-Za-z][A-Za-z0-9_.:-]{0,127}$/D';
    private const HANDLER_PATTERN = '/^[a-z][a-z0-9_:-]{0,127}$/D';

    /** @var array<string,true>|null */
    private readonly ?array $guardHandlers;

    /** @var array<string,true>|null */
    private readonly ?array $actionHandlers;

    /**
     * @param list<string>|null $guardHandlerNames
     * @param list<string>|null $actionHandlerNames
     */
    public function __construct(
        private readonly FsmDefinitionValidatorInterface $validator = new FsmDefinitionValidator(),
        ?array $guardHandlerNames = null,
        ?array $actionHandlerNames = null
    ) {
        $this->guardHandlers = $this->normalizeHandlerCatalog(
            $guardHandlerNames,
            'GUARD'
        );
        $this->actionHandlers = $this->normalizeHandlerCatalog(
            $actionHandlerNames,
            'ACTION'
        );
    }

    public function apply(array $definition, array $command): array
    {
        $this->validator->assertValid($definition);
        $this->assertRegisteredHandlerReferences($definition);

        $operation = trim((string) ($command['operation'] ?? ''));
        $refactor = [];

        switch ($operation) {
            case 'state.create':
                $definition = $this->createState($definition, $command);
                break;
            case 'state.rename':
                [$definition, $refactor] = $this->renameState(
                    $definition,
                    $command
                );
                break;
            case 'state.delete':
                $definition = $this->deleteState($definition, $command);
                break;
            case 'state.update':
                throw new \RuntimeException(
                    'OPUS_EFSM_STATE_UPDATE_NOT_SEMANTIC'
                );
            case 'signal.create':
                $definition = $this->createSignal($definition, $command);
                break;
            case 'transition.create':
                $definition = $this->createTransition(
                    $definition,
                    $command
                );
                break;
            case 'transition.rename':
                [$definition, $refactor] = $this->renameTransition(
                    $definition,
                    $command
                );
                break;
            case 'transition.delete':
                [$definition, $refactor] = $this->deleteTransition(
                    $definition,
                    $command
                );
                break;
            case 'transition.handlers.update':
                $definition = $this->updateTransitionHandlers(
                    $definition,
                    $command
                );
                break;
            default:
                throw new \RuntimeException(
                    'OPUS_EFSM_EDITOR_OPERATION_UNKNOWN:' . $operation
                );
        }

        $validation = $this->validator->validate($definition);
        if (!$validation['valid']) {
            $first = $validation['diagnostics'][0] ?? null;
            throw new \RuntimeException(
                is_array($first) && ($first['code'] ?? '') !== ''
                    ? (string) $first['code']
                    : 'OPUS_EFSM_DEFINITION_INVALID'
            );
        }
        $this->assertRegisteredHandlerReferences($definition);

        return [
            'definition' => $definition,
            'diagnostics' => $validation['diagnostics'],
            'operation' => $operation,
            'refactor' => $refactor,
        ];
    }

    /** @param array<string,mixed> $definition @param array<string,mixed> $command @return array<string,mixed> */
    private function createState(array $definition, array $command): array
    {
        $state = $command['state'] ?? null;
        if (!is_array($state)) {
            throw new \RuntimeException(
                'OPUS_EFSM_STATE_CREATE_PAYLOAD_INVALID'
            );
        }

        foreach (array_keys($state) as $field) {
            if ($field !== 'id') {
                throw new \RuntimeException(
                    'OPUS_EFSM_STATE_FIELD_FORBIDDEN:' . (string) $field
                );
            }
        }

        $id = $this->stateId($state['id'] ?? null);
        foreach ((array) ($definition['states'] ?? []) as $existing) {
            if (is_array($existing)
                && (string) ($existing['id'] ?? '') === $id) {
                throw new \RuntimeException(
                    'OPUS_EFSM_STATE_ID_DUPLICATE:' . $id
                );
            }
        }

        $definition['states'][] = ['id' => $id];
        return $definition;
    }

    /** @param array<string,mixed> $definition @param array<string,mixed> $command @return array<string,mixed> */
    private function createSignal(array $definition, array $command): array
    {
        $signal = $command['signal'] ?? null;
        if (!is_array($signal) || array_is_list($signal)) {
            throw new \RuntimeException(
                'OPUS_EFSM_SIGNAL_CREATE_PAYLOAD_INVALID'
            );
        }
        foreach (array_keys($signal) as $field) {
            if (!in_array($field, ['id', 'origin', 'type'], true)) {
                throw new \RuntimeException(
                    'OPUS_EFSM_SIGNAL_FIELD_FORBIDDEN:' . (string) $field
                );
            }
        }

        $id = $this->signalId($signal['id'] ?? null);
        $origin = strtolower(trim((string) ($signal['origin'] ?? '')));
        if (!in_array($origin, ['user', 'automatic'], true)) {
            throw new \RuntimeException(
                'OPUS_EFSM_SIGNAL_ORIGIN_INVALID:' . $origin
            );
        }
        $type = strtolower(trim((string) ($signal['type'] ?? '')));
        if (!in_array(
            $type,
            ['navigation', 'command', 'outcome', 'event', 'system'],
            true
        )) {
            throw new \RuntimeException(
                'OPUS_EFSM_SIGNAL_TYPE_INVALID:' . $type
            );
        }
        foreach ((array) ($definition['signals'] ?? []) as $existing) {
            if (is_array($existing)
                && (string) ($existing['id'] ?? '') === $id) {
                throw new \RuntimeException(
                    'OPUS_EFSM_SIGNAL_ID_DUPLICATE:' . $id
                );
            }
        }

        $definition['signals'][] = [
            'id' => $id,
            'origin' => $origin,
            'type' => $type,
        ];
        return $definition;
    }

    /** @param array<string,mixed> $definition @param array<string,mixed> $command @return array<string,mixed> */
    private function createTransition(
        array $definition,
        array $command
    ): array {
        $transition = $command['transition'] ?? null;
        if (!is_array($transition) || array_is_list($transition)) {
            throw new \RuntimeException(
                'OPUS_EFSM_TRANSITION_CREATE_PAYLOAD_INVALID'
            );
        }
        foreach (array_keys($transition) as $field) {
            if (!in_array(
                $field,
                ['id', 'from', 'signal', 'next_state'],
                true
            )) {
                throw new \RuntimeException(
                    'OPUS_EFSM_TRANSITION_FIELD_FORBIDDEN:'
                    . (string) $field
                );
            }
        }

        $id = $this->transitionId($transition['id'] ?? null);
        $from = $this->stateId($transition['from'] ?? null);
        $signal = $this->signalId($transition['signal'] ?? null);
        $target = $this->stateId($transition['next_state'] ?? null);
        $this->stateIndex($definition, $from);
        $this->stateIndex($definition, $target);
        $this->signalIndex($definition, $signal);
        foreach ((array) ($definition['transitions'] ?? []) as $existing) {
            if (is_array($existing)
                && (string) ($existing['id'] ?? '') === $id) {
                throw new \RuntimeException(
                    'OPUS_EFSM_TRANSITION_ID_DUPLICATE:' . $id
                );
            }
        }

        $definition['transitions'][] = [
            'id' => $id,
            'from' => $from,
            'signal' => $signal,
            'next_state' => $target,
        ];
        return $definition;
    }

    /** @param array<string,mixed> $definition @param array<string,mixed> $command @return array{0:array<string,mixed>,1:array<string,string>} */
    private function renameTransition(array $definition, array $command): array
    {
        $old = $this->transitionId($command['transition_id'] ?? null);
        $new = $this->transitionId($command['new_id'] ?? null);
        if ($old === $new) {
            return [$definition, []];
        }

        $index = $this->transitionIndex($definition, $old);
        foreach ((array) ($definition['transitions'] ?? []) as $existing) {
            if (is_array($existing)
                && (string) ($existing['id'] ?? '') === $new) {
                throw new \RuntimeException(
                    'OPUS_EFSM_TRANSITION_ID_DUPLICATE:' . $new
                );
            }
        }

        $transition = $definition['transitions'][$index];
        if (!is_array($transition)) {
            throw new \RuntimeException(
                'OPUS_EFSM_TRANSITION_ENTRY_INVALID:' . $old
            );
        }
        $transition['id'] = $new;
        $definition['transitions'][$index] = $transition;

        return [$definition, [
            'transition_old' => $old,
            'transition_new' => $new,
        ]];
    }

    /** @param array<string,mixed> $definition @param array<string,mixed> $command @return array{0:array<string,mixed>,1:array<string,string>} */
    private function renameState(array $definition, array $command): array
    {
        $old = $this->stateId($command['state_id'] ?? null);
        $new = $this->stateId($command['new_id'] ?? null);
        if ($old === $new) {
            return [$definition, []];
        }

        $index = $this->stateIndex($definition, $old);
        foreach ((array) ($definition['states'] ?? []) as $existing) {
            if (is_array($existing)
                && (string) ($existing['id'] ?? '') === $new) {
                throw new \RuntimeException(
                    'OPUS_EFSM_STATE_ID_DUPLICATE:' . $new
                );
            }
        }

        $state = $definition['states'][$index];
        if (!is_array($state)) {
            throw new \RuntimeException(
                'OPUS_EFSM_STATE_INVALID:' . $old
            );
        }
        $state['id'] = $new;
        $definition['states'][$index] = $state;

        foreach (['initial_state', 'final_state'] as $field) {
            if (($definition[$field] ?? null) === $old) {
                $definition[$field] = $new;
            }
        }

        foreach ((array) ($definition['transitions'] ?? []) as $transitionIndex => $transition) {
            if (!is_array($transition)) {
                continue;
            }
            if (($transition['from'] ?? null) === $old) {
                $transition['from'] = $new;
            }
            if (($transition['next_state'] ?? null) === $old) {
                $transition['next_state'] = $new;
            }
            if (($transition['nextState'] ?? null) === $old) {
                $transition['nextState'] = $new;
            }
            if (is_array($transition['from_states'] ?? null)) {
                $transition['from_states'] = array_map(
                    static fn (mixed $source): mixed =>
                        $source === $old ? $new : $source,
                    $transition['from_states']
                );
            }
            $definition['transitions'][$transitionIndex] = $transition;
        }

        return [
            $definition,
            ['state_old' => $old, 'state_new' => $new],
        ];
    }

    /** @param array<string,mixed> $definition @param array<string,mixed> $command @return array<string,mixed> */
    private function deleteState(array $definition, array $command): array
    {
        $id = $this->stateId($command['state_id'] ?? null);
        $confirmation = trim((string) (
            $command['confirmation'] ?? ''
        ));
        if (!hash_equals($id, $confirmation)) {
            throw new \RuntimeException(
                'OPUS_EFSM_STATE_DELETE_CONFIRMATION_INVALID'
            );
        }
        if (($definition['initial_state'] ?? null) === $id) {
            throw new \RuntimeException(
                'OPUS_EFSM_INITIAL_STATE_DELETE_FORBIDDEN:' . $id
            );
        }
        if (($definition['final_state'] ?? null) === $id) {
            throw new \RuntimeException(
                'OPUS_EFSM_FINAL_STATE_DELETE_FORBIDDEN:' . $id
            );
        }

        foreach ((array) ($definition['transitions'] ?? []) as $transition) {
            if (!is_array($transition)) {
                continue;
            }
            $fromStates = is_array(
                $transition['from_states'] ?? null
            )
                ? $transition['from_states']
                : [];
            if (($transition['from'] ?? null) === $id
                || ($transition['next_state'] ?? null) === $id
                || ($transition['nextState'] ?? null) === $id
                || in_array($id, $fromStates, true)) {
                throw new \RuntimeException(
                    'OPUS_EFSM_STATE_DELETE_DEPENDENCY:' . $id
                );
            }
        }

        $index = $this->stateIndex($definition, $id);
        array_splice($definition['states'], $index, 1);
        return $definition;
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $command
     * @return array{0:array<string,mixed>,1:array<string,string>}
     */
    private function deleteTransition(
        array $definition,
        array $command
    ): array {
        $id = $this->transitionId(
            $command['transition_id'] ?? null
        );
        $confirmation = trim((string) (
            $command['confirmation'] ?? ''
        ));
        if (!hash_equals($id, $confirmation)) {
            throw new \RuntimeException(
                'OPUS_EFSM_TRANSITION_DELETE_CONFIRMATION_INVALID'
            );
        }

        $index = $this->transitionIndex($definition, $id);
        $transition = $definition['transitions'][$index] ?? null;
        if (!is_array($transition)) {
            throw new \RuntimeException(
                'OPUS_EFSM_TRANSITION_INVALID:' . $id
            );
        }
        $signal = $this->signalId($transition['signal'] ?? null);
        array_splice($definition['transitions'], $index, 1);

        $signalStillUsed = false;
        foreach ((array) ($definition['transitions'] ?? []) as $remaining) {
            if (is_array($remaining)
                && (string) ($remaining['signal'] ?? '') === $signal) {
                $signalStillUsed = true;
                break;
            }
        }

        return [
            $definition,
            [
                'transition_deleted' => $id,
                'signal_orphaned' => $signalStillUsed ? '' : $signal,
            ],
        ];
    }

    /** @param array<string,mixed> $definition @param array<string,mixed> $command @return array<string,mixed> */
    private function updateTransitionHandlers(
        array $definition,
        array $command
    ): array {
        if ($this->guardHandlers === null
            || $this->actionHandlers === null) {
            throw new \RuntimeException(
                'OPUS_EFSM_HANDLER_CATALOG_REQUIRED'
            );
        }

        $id = $this->transitionId(
            $command['transition_id'] ?? null
        );
        $guards = $this->handlerList(
            $command['guards'] ?? null,
            'GUARD'
        );
        $actions = $this->handlerList(
            $command['actions'] ?? null,
            'ACTION'
        );

        foreach ($guards as $guard) {
            if (!isset($this->guardHandlers[$guard])) {
                throw new \RuntimeException(
                    'OPUS_EFSM_GUARD_HANDLER_MISSING:' . $guard
                );
            }
        }
        foreach ($actions as $action) {
            if (!isset($this->actionHandlers[$action])) {
                throw new \RuntimeException(
                    'OPUS_EFSM_ACTION_HANDLER_MISSING:' . $action
                );
            }
        }

        $index = $this->transitionIndex($definition, $id);
        $transition = $definition['transitions'][$index];
        if (!is_array($transition)) {
            throw new \RuntimeException(
                'OPUS_EFSM_TRANSITION_INVALID:' . $id
            );
        }
        if (($transition['interrupt'] ?? null) === 'nmi'
            && $guards !== []) {
            throw new \RuntimeException(
                'OPUS_EFSM_NMI_GUARD_FORBIDDEN:' . $id
            );
        }

        unset(
            $transition['guard'],
            $transition['action']
        );
        if ($guards === []) {
            unset($transition['guards']);
        } else {
            $transition['guards'] = $guards;
        }
        if ($actions === []) {
            unset($transition['actions']);
        } else {
            $transition['actions'] = $actions;
        }
        $definition['transitions'][$index] = $transition;

        return $definition;
    }

    private function stateId(mixed $value): string
    {
        $id = is_string($value) ? trim($value) : '';
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            throw new \RuntimeException(
                'OPUS_EFSM_STATE_ID_INVALID:' . $id
            );
        }
        return $id;
    }

    private function transitionId(mixed $value): string
    {
        $id = is_string($value) ? trim($value) : '';
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            throw new \RuntimeException(
                'OPUS_EFSM_TRANSITION_ID_INVALID:' . $id
            );
        }
        return $id;
    }

    private function signalId(mixed $value): string
    {
        $id = is_string($value) ? trim($value) : '';
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            throw new \RuntimeException(
                'OPUS_EFSM_SIGNAL_ID_INVALID:' . $id
            );
        }
        return $id;
    }

    /**
     * @param list<string>|null $names
     * @return array<string,true>|null
     */
    private function normalizeHandlerCatalog(
        ?array $names,
        string $kind
    ): ?array {
        if ($names === null) {
            return null;
        }
        if (!array_is_list($names)) {
            throw new \InvalidArgumentException(
                'OPUS_EFSM_' . $kind . '_HANDLER_CATALOG_INVALID'
            );
        }

        $set = [];
        foreach ($names as $name) {
            $id = is_string($name) ? trim($name) : '';
            if (preg_match(self::HANDLER_PATTERN, $id) !== 1) {
                throw new \InvalidArgumentException(
                    'OPUS_EFSM_' . $kind
                    . '_HANDLER_CATALOG_ENTRY_INVALID'
                );
            }
            if (isset($set[$id])) {
                throw new \InvalidArgumentException(
                    'OPUS_EFSM_' . $kind
                    . '_HANDLER_CATALOG_DUPLICATE:' . $id
                );
            }
            $set[$id] = true;
        }
        return $set;
    }

    /** @return list<string> */
    private function handlerList(
        mixed $value,
        string $kind
    ): array {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \RuntimeException(
                'OPUS_EFSM_' . $kind . '_HANDLER_LIST_INVALID'
            );
        }

        $result = [];
        $seen = [];
        foreach ($value as $item) {
            $id = is_string($item) ? trim($item) : '';
            if (preg_match(self::HANDLER_PATTERN, $id) !== 1) {
                throw new \RuntimeException(
                    'OPUS_EFSM_' . $kind
                    . '_HANDLER_NAME_INVALID:' . $id
                );
            }
            if (isset($seen[$id])) {
                throw new \RuntimeException(
                    'OPUS_EFSM_' . $kind
                    . '_HANDLER_DUPLICATE:' . $id
                );
            }
            $seen[$id] = true;
            $result[] = $id;
        }
        return $result;
    }

    /** @param array<string,mixed> $definition */
    private function assertRegisteredHandlerReferences(
        array $definition
    ): void {
        if ($this->guardHandlers === null
            && $this->actionHandlers === null) {
            return;
        }

        foreach ((array) ($definition['transitions'] ?? []) as $transition) {
            if (!is_array($transition)) {
                continue;
            }

            if ($this->guardHandlers !== null) {
                foreach ($this->definitionHandlerList(
                    $transition['guards']
                        ?? ($transition['guard'] ?? [])
                ) as $guard) {
                    if (!isset($this->guardHandlers[$guard])) {
                        throw new \RuntimeException(
                            'OPUS_EFSM_GUARD_HANDLER_MISSING:'
                            . $guard
                        );
                    }
                }
            }

            if ($this->actionHandlers !== null) {
                foreach ($this->definitionHandlerList(
                    $transition['actions']
                        ?? ($transition['action'] ?? [])
                ) as $action) {
                    if (!isset($this->actionHandlers[$action])) {
                        throw new \RuntimeException(
                            'OPUS_EFSM_ACTION_HANDLER_MISSING:'
                            . $action
                        );
                    }
                }
            }
        }
    }

    /** @return list<string> */
    private function definitionHandlerList(mixed $value): array
    {
        if (is_string($value)) {
            $value = trim($value);
            return $value === '' ? [] : [$value];
        }
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter(
            array_map(
                static fn (mixed $item): string =>
                    is_string($item) ? trim($item) : '',
                $value
            ),
            static fn (string $item): bool => $item !== ''
        ));
    }

    /** @param array<string,mixed> $definition */
    private function stateIndex(array $definition, string $id): int
    {
        foreach ((array) ($definition['states'] ?? []) as $index => $state) {
            if (is_array($state)
                && (string) ($state['id'] ?? '') === $id) {
                return (int) $index;
            }
        }
        throw new \RuntimeException(
            'OPUS_EFSM_STATE_UNKNOWN:' . $id
        );
    }

    /** @param array<string,mixed> $definition */
    private function transitionIndex(
        array $definition,
        string $id
    ): int {
        foreach ((array) ($definition['transitions'] ?? []) as $index => $transition) {
            if (is_array($transition)
                && (string) ($transition['id'] ?? '') === $id) {
                return (int) $index;
            }
        }
        throw new \RuntimeException(
            'OPUS_EFSM_TRANSITION_UNKNOWN:' . $id
        );
    }

    /** @param array<string,mixed> $definition */
    private function signalIndex(array $definition, string $id): int
    {
        foreach ((array) ($definition['signals'] ?? []) as $index => $signal) {
            if (is_array($signal)
                && (string) ($signal['id'] ?? '') === $id) {
                return (int) $index;
            }
        }
        throw new \RuntimeException(
            'OPUS_EFSM_SIGNAL_UNKNOWN:' . $id
        );
    }
}
