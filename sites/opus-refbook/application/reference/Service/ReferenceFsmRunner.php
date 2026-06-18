<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Service;

use Opus\Fsm\StateDefinition;
use Opus\Fsm\StateMachine;
use Opus\Fsm\TransitionDefinition;
use RuntimeException;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Orchestrate RefBook runtime data preparation with the shared Opus FSM.
 *
 * Contract:
 *   FSM orchestration only. No routing, no HTML rendering, no data fallback.
 */
final class ReferenceFsmRunner implements ReferenceSnapshotRepositoryInterface
{
    private ?array $snapshot = null;

    public function __construct(private readonly ReferenceSnapshotRepositoryInterface $repository)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function health(): array
    {
        $machine = $this->machine();
        $trace = [];
        $trace[] = $machine->currentState();
        $trace[] = $machine->apply('PREPARE_PROVIDER')->toState();

        return [
            'ok' => true,
            'api_version' => 'opus-refbook-internal/v1',
            'read_only' => true,
            'runner' => 'OpusRefBook\\Reference\\Service\\ReferenceFsmRunner',
            'source' => 'shared-opus-provider',
            'fsm' => [
                'state' => $machine->currentState(),
                'trace' => $trace,
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function load(): array
    {
        return $this->snapshot();
    }

    /**
     * @return array<string,mixed>
     */
    public function snapshot(): array
    {
        if ($this->snapshot !== null) {
            return $this->snapshot;
        }

        $machine = $this->machine();
        $trace = [];
        $trace[] = $machine->currentState();
        $trace[] = $machine->apply('PREPARE_PROVIDER')->toState();
        $trace[] = $machine->apply('REQUIRE_SNAPSHOT')->toState();

        $snapshot = $this->repository->load();
        $trace[] = $machine->apply('BUILD_SNAPSHOT')->toState();

        $this->assertSnapshot($snapshot);
        $trace[] = $machine->apply('VALIDATE_SNAPSHOT')->toState();
        $trace[] = $machine->apply('PREPARE_VIEWMODEL')->toState();

        $snapshot['runtime']['fsm'] = [
            'state' => $machine->currentState(),
            'trace' => $trace,
        ];

        $this->snapshot = $snapshot;

        return $this->snapshot;
    }

    private function machine(): StateMachine
    {
        if (!class_exists(StateMachine::class)) {
            throw new RuntimeException('OPUS_REFBOOK_FSM_CLASS_MISSING=' . StateMachine::class);
        }

        return new StateMachine(
            [
                new StateDefinition('REFBOOK_BOOT', 'RefBook boot'),
                new StateDefinition('API_PROVIDER_READY', 'API provider ready'),
                new StateDefinition('SNAPSHOT_REQUIRED', 'Snapshot required'),
                new StateDefinition('SNAPSHOT_BUILT', 'Snapshot built'),
                new StateDefinition('SNAPSHOT_VALIDATED', 'Snapshot validated'),
                new StateDefinition('VIEWMODEL_READY', 'ViewModel ready'),
            ],
            [
                new TransitionDefinition('REFBOOK_BOOT', 'PREPARE_PROVIDER', 'API_PROVIDER_READY'),
                new TransitionDefinition('API_PROVIDER_READY', 'REQUIRE_SNAPSHOT', 'SNAPSHOT_REQUIRED'),
                new TransitionDefinition('SNAPSHOT_REQUIRED', 'BUILD_SNAPSHOT', 'SNAPSHOT_BUILT'),
                new TransitionDefinition('SNAPSHOT_BUILT', 'VALIDATE_SNAPSHOT', 'SNAPSHOT_VALIDATED'),
                new TransitionDefinition('SNAPSHOT_VALIDATED', 'PREPARE_VIEWMODEL', 'VIEWMODEL_READY'),
            ],
            'REFBOOK_BOOT'
        );
    }

    /**
     * @param array<string,mixed> $snapshot
     */
    private function assertSnapshot(array $snapshot): void
    {
        if (($snapshot['schema'] ?? null) !== 'OPUS_REFBOOK_RUNTIME_MANIFEST_V1') {
            throw new RuntimeException('OPUS_REFBOOK_RUNTIME_SCHEMA_INVALID=' . (string) ($snapshot['schema'] ?? 'missing'));
        }

        if (($snapshot['runtime']['read_only'] ?? null) !== true) {
            throw new RuntimeException('OPUS_REFBOOK_RUNTIME_NOT_READ_ONLY');
        }

        if (!isset($snapshot['symbols']) || !is_array($snapshot['symbols'])) {
            throw new RuntimeException('OPUS_REFBOOK_RUNTIME_SYMBOLS_MISSING');
        }
    }
}
