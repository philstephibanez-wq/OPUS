<?php

declare(strict_types=1);

namespace Opus\Fsm;

use ASAP\RefBook\Attribute\OpusRefBookClass;
use ASAP\RefBook\Attribute\OpusRefBookMethod;
use ASAP\RefBook\Contract\RefBookInspectableInterface;

/**
 * PUBLIC DTO
 *
 * Role:
 *   Holds explicit FSM memory values.
 *
 * Responsibility:
 *   Provide controlled key/value memory for a StateMachine instance.
 *
 * Contract:
 *   Memory keys must be explicit non-empty strings. No implicit serialization fallback.
 *
 * @package ASAP\Fsm
 * OPUS_REFBOOK:
 *   domain: FSM
 *   role: Official memory holder for current FSM state.
 *   contract:
 *     - stores current state only through explicit API
 *     - does not decide transitions
 *     - does not silently invent default states
 *   examples:
 *     - fsm-basic-transition
 *   diagrams:
 *     - fsm-runtime
 * END_OPUS_REFBOOK
 */
#[OpusRefBookClass(
    domain: 'FSM',
    role: 'Hold explicit memory values for one FSM runtime instance',
    responsibility: 'Provide controlled key/value storage for StateMachine actions without owning transition selection.',
    contracts: [
        'Memory keys must be explicit non-empty strings.',
        'Missing keys fail explicitly.',
        'The memory object does not decide transitions or create fallback states.',
    ],
    examples: ['fsm-basic-transition', 'fsm-action'],
    diagrams: ['fsm-runtime'],
    introducedIn: 'P112Q3E1'
)]
final class StateMemory implements RefBookInspectableInterface
{
    /** @var array<string,mixed> */
    private array $values = [];

    #[OpusRefBookMethod(
        role: 'Expose the RefBook domain for FSM memory',
        behavior: 'Returns the stable RefBook domain used by scanners, snapshots and OPUS_REF_BOOK renderers.',
        preconditions: ['none'],
        postconditions: ['The returned domain is FSM.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-refbook-domain'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
    public static function refBookDomain(): string
    {
        return 'FSM';
    }

    /**
     * PUBLIC API
     *
     * @param string $key Memory key.
     * @param mixed $value Memory value.
     *
     * @return void
     *
     * @throws StateMachineException When the key is empty.
     */
    #[OpusRefBookMethod(
        role: 'Set one explicit FSM memory value',
        behavior: 'Validates the memory key and stores the provided value for later retrieval by declared FSM actions.',
        preconditions: ['The memory key must not be empty after trimming.'],
        postconditions: ['The key exists in memory and maps to the provided value.'],
        sideEffects: ['Mutates this StateMemory instance.'],
        errors: ['FSM_MEMORY_CONTRACT_FAILED'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-action'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
    public function set(string $key, mixed $value): void
    {
        $key = trim($key);

        if ($key === '') {
            throw StateMachineException::contract(StateMachineException::MEMORY_CONTRACT_FAILED, 'Memory key must not be empty.');
        }

        $this->values[$key] = $value;
    }

    /**
     * PUBLIC API
     *
     * @param string $key Memory key.
     *
     * @return mixed Memory value.
     *
     * @throws StateMachineException When the key does not exist.
     */
    #[OpusRefBookMethod(
        role: 'Read one explicit FSM memory value',
        behavior: 'Returns the value stored under an existing memory key and fails explicitly when the key is absent.',
        preconditions: ['The requested key exists in memory.'],
        postconditions: ['The stored value is returned without modifying memory.'],
        sideEffects: ['none'],
        errors: ['FSM_MEMORY_CONTRACT_FAILED'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-action'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
    public function get(string $key): mixed
    {
        if (!array_key_exists($key, $this->values)) {
            throw StateMachineException::contract(StateMachineException::MEMORY_CONTRACT_FAILED, 'Memory key not found: ' . $key);
        }

        return $this->values[$key];
    }

    /**
     * PUBLIC API
     *
     * @return array<string,mixed> Memory values for controlled export.
     */
    #[OpusRefBookMethod(
        role: 'Export explicit FSM memory values',
        behavior: 'Returns the current memory map for diagnostics, reports or controlled consumers.',
        preconditions: ['The StateMemory instance exists.'],
        postconditions: ['The returned array mirrors current memory values.'],
        sideEffects: ['none'],
        errors: ['none'],
        testRefs: ['tests/Contract/RefBookFsmMetadataContractTest.php'],
        examples: ['fsm-action'],
        diagrams: ['fsm-runtime'],
        introducedIn: 'P112Q3E1'
    )]
    public function export(): array
    {
        return $this->values;
    }
}
