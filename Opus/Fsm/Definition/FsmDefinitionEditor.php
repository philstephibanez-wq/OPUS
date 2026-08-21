<?php
declare(strict_types=1);

namespace Opus\Fsm\Definition;

/** Dependency-safe semantic editor for OPUS EFSM definitions. */
final class FsmDefinitionEditor implements FsmDefinitionEditorInterface
{
    private const ID_PATTERN = '/^[A-Za-z][A-Za-z0-9_.:-]{0,127}$/D';
    private const STATE_FIELDS = [
        'type' => true,
        'module' => true,
        'route' => true,
        'template' => true,
        'requires_auth' => true,
        'requires_current_app' => true,
        'navigation' => true,
        'diagram' => true,
        'title_key' => true,
        'summary_key' => true,
        'final' => true,
        'terminal' => true,
    ];

    public function __construct(
        private readonly FsmDefinitionValidatorInterface $validator = new FsmDefinitionValidator()
    ) {
    }

    public function apply(array $definition, array $command): array
    {
        $this->validator->assertValid($definition);
        $operation = trim((string) ($command['operation'] ?? ''));
        $refactor = [];

        switch ($operation) {
            case 'state.create':
                $definition = $this->createState($definition, $command);
                break;
            case 'state.update':
                $definition = $this->updateState($definition, $command);
                break;
            case 'state.rename':
                [$definition, $refactor] = $this->renameState($definition, $command);
                break;
            case 'state.delete':
                $definition = $this->deleteState($definition, $command);
                break;
            default:
                throw new \RuntimeException('OPUS_EFSM_EDITOR_OPERATION_UNKNOWN:' . $operation);
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
            throw new \RuntimeException('OPUS_EFSM_STATE_CREATE_PAYLOAD_INVALID');
        }
        $id = $this->stateId($state['id'] ?? null);
        foreach ((array) ($definition['states'] ?? []) as $existing) {
            if (is_array($existing) && (string) ($existing['id'] ?? '') === $id) {
                throw new \RuntimeException('OPUS_EFSM_STATE_ID_DUPLICATE:' . $id);
            }
        }
        $normalized = ['id' => $id];
        foreach (self::STATE_FIELDS as $field => $_) {
            if (array_key_exists($field, $state)) {
                $normalized[$field] = $state[$field];
            }
        }
        $definition['states'][] = $normalized;
        return $definition;
    }

    /** @param array<string,mixed> $definition @param array<string,mixed> $command @return array<string,mixed> */
    private function updateState(array $definition, array $command): array
    {
        $id = $this->stateId($command['state_id'] ?? null);
        $changes = $command['changes'] ?? null;
        if (!is_array($changes) || array_key_exists('id', $changes)) {
            throw new \RuntimeException('OPUS_EFSM_STATE_UPDATE_PAYLOAD_INVALID');
        }
        $index = $this->stateIndex($definition, $id);
        $state = $definition['states'][$index];
        if (!is_array($state)) {
            throw new \RuntimeException('OPUS_EFSM_STATE_INVALID:' . $id);
        }
        foreach ($changes as $field => $value) {
            if (!is_string($field) || !isset(self::STATE_FIELDS[$field])) {
                throw new \RuntimeException('OPUS_EFSM_STATE_FIELD_FORBIDDEN:' . (string) $field);
            }
            $state[$field] = $value;
        }
        $definition['states'][$index] = $state;
        return $definition;
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
            if (is_array($existing) && (string) ($existing['id'] ?? '') === $new) {
                throw new \RuntimeException('OPUS_EFSM_STATE_ID_DUPLICATE:' . $new);
            }
        }
        $state = $definition['states'][$index];
        if (!is_array($state)) {
            throw new \RuntimeException('OPUS_EFSM_STATE_INVALID:' . $old);
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
                    static fn (mixed $source): mixed => $source === $old ? $new : $source,
                    $transition['from_states']
                );
            }
            $definition['transitions'][$transitionIndex] = $transition;
        }

        return [$definition, ['state_old' => $old, 'state_new' => $new]];
    }

    /** @param array<string,mixed> $definition @param array<string,mixed> $command @return array<string,mixed> */
    private function deleteState(array $definition, array $command): array
    {
        $id = $this->stateId($command['state_id'] ?? null);
        $confirmation = trim((string) ($command['confirmation'] ?? ''));
        if (!hash_equals($id, $confirmation)) {
            throw new \RuntimeException('OPUS_EFSM_STATE_DELETE_CONFIRMATION_INVALID');
        }
        if (($definition['initial_state'] ?? null) === $id) {
            throw new \RuntimeException('OPUS_EFSM_INITIAL_STATE_DELETE_FORBIDDEN:' . $id);
        }
        if (($definition['final_state'] ?? null) === $id) {
            throw new \RuntimeException('OPUS_EFSM_FINAL_STATE_DELETE_FORBIDDEN:' . $id);
        }
        foreach ((array) ($definition['transitions'] ?? []) as $transition) {
            if (!is_array($transition)) {
                continue;
            }
            $fromStates = is_array($transition['from_states'] ?? null)
                ? $transition['from_states']
                : [];
            if (($transition['from'] ?? null) === $id
                || ($transition['next_state'] ?? null) === $id
                || ($transition['nextState'] ?? null) === $id
                || in_array($id, $fromStates, true)) {
                throw new \RuntimeException('OPUS_EFSM_STATE_DELETE_DEPENDENCY:' . $id);
            }
        }
        $index = $this->stateIndex($definition, $id);
        array_splice($definition['states'], $index, 1);
        return $definition;
    }

    private function stateId(mixed $value): string
    {
        $id = is_string($value) ? trim($value) : '';
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            throw new \RuntimeException('OPUS_EFSM_STATE_ID_INVALID:' . $id);
        }
        return $id;
    }

    /** @param array<string,mixed> $definition */
    private function stateIndex(array $definition, string $id): int
    {
        foreach ((array) ($definition['states'] ?? []) as $index => $state) {
            if (is_array($state) && (string) ($state['id'] ?? '') === $id) {
                return (int) $index;
            }
        }
        throw new \RuntimeException('OPUS_EFSM_STATE_UNKNOWN:' . $id);
    }
}