<?php
declare(strict_types=1);

use Opus\File\Json;

/** Read-only projection of the selected application's canonical FSM through secured REST. */
final class OwasysApplicationFsmModel
{
    private const GENERATED_SOURCE_PATH = 'config/application.fsm.json';
    private const SYSTEM_SOURCE_PATH = 'config/fsm.json';

    /** @var array<string,true> */
    private const SYSTEM_APPLICATIONS = [
        'owasys-front' => true,
        'owasys-back' => true,
    ];

    public function __construct(private readonly OwasysSourceModel $source)
    {
    }

    /**
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function snapshot(string $siteId, array $actor): array
    {
        $sourcePath = isset(self::SYSTEM_APPLICATIONS[$siteId])
            ? self::SYSTEM_SOURCE_PATH
            : self::GENERATED_SOURCE_PATH;
        $file = $this->source->read($siteId, $sourcePath, $actor);
        $definition = Json::instance()->parse(
            (string) ($file['content'] ?? ''),
            $sourcePath
        );

        $contract = trim((string) ($definition['contract'] ?? ''));
        if (!in_array(
            $contract,
            [
                'OPUS_APPLICATION_FSM_V1',
                'OWASYS_NAVIGATION_FSM_V1',
                'OWASYS_BACK_FSM_V1',
            ],
            true
        )) {
            throw new RuntimeException('OWASYS_APPLICATION_FSM_CONTRACT_INVALID');
        }
        $states = is_array($definition['states'] ?? null)
            ? $definition['states']
            : [];
        $transitions = is_array($definition['transitions'] ?? null)
            ? $definition['transitions']
            : [];
        if ($states === []) {
            throw new RuntimeException('OWASYS_APPLICATION_FSM_STATES_EMPTY');
        }

        $stateIds = [];
        foreach ($states as $state) {
            if (!is_array($state)) {
                throw new RuntimeException('OWASYS_APPLICATION_FSM_STATE_INVALID');
            }
            $id = trim((string) ($state['id'] ?? ''));
            if ($id === '' || isset($stateIds[$id])) {
                throw new RuntimeException('OWASYS_APPLICATION_FSM_STATE_ID_INVALID');
            }
            $stateIds[$id] = true;
        }

        $initial = trim((string) ($definition['initial_state'] ?? ''));
        if ($initial === '' || !isset($stateIds[$initial])) {
            throw new RuntimeException('OWASYS_APPLICATION_FSM_INITIAL_STATE_INVALID');
        }
        foreach ($transitions as $transition) {
            if (!is_array($transition)) {
                throw new RuntimeException('OWASYS_APPLICATION_FSM_TRANSITION_INVALID');
            }
            $from = trim((string) ($transition['from'] ?? ''));
            $to = trim((string) (
                $transition['next_state']
                ?? $transition['nextState']
                ?? ''
            ));
            $signal = trim((string) ($transition['signal'] ?? ''));
            if ($signal === '' || $to === '' || !isset($stateIds[$to])) {
                throw new RuntimeException('OWASYS_APPLICATION_FSM_TRANSITION_INVALID');
            }

            if (($transition['scope'] ?? null) === 'global') {
                $sources = is_array($transition['from_states'] ?? null)
                    ? $transition['from_states']
                    : [];
                if ($sources === []) {
                    throw new RuntimeException(
                        'OWASYS_APPLICATION_FSM_GLOBAL_SOURCES_EMPTY'
                    );
                }
                foreach ($sources as $source) {
                    if (!is_string($source) || !isset($stateIds[$source])) {
                        throw new RuntimeException(
                            'OWASYS_APPLICATION_FSM_TRANSITION_SOURCE_INVALID'
                        );
                    }
                }
            } elseif ($from !== '*' && !isset($stateIds[$from])) {
                throw new RuntimeException(
                    'OWASYS_APPLICATION_FSM_TRANSITION_SOURCE_INVALID'
                );
            }
        }

        $declaredSite = trim((string) ($definition['site_id'] ?? ''));
        if ($declaredSite !== '' && $declaredSite !== $siteId) {
            throw new RuntimeException('OWASYS_APPLICATION_FSM_SITE_MISMATCH');
        }

        $hash = trim((string) ($file['sha256'] ?? ''));
        if (preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
            throw new RuntimeException('OWASYS_APPLICATION_FSM_HASH_INVALID');
        }

        return [
            'contract' => 'OWASYS_APPLICATION_FSM_SNAPSHOT_V1',
            'application_id' => $siteId,
            'source_path' => $sourcePath,
            'sha256' => $hash,
            'state_count' => count($states),
            'transition_count' => count($transitions),
            'definition' => $definition,
            'diagram' => \OPUS_FSM_Diagram::renderDefinition(
                $definition,
                '',
                [],
                []
            ),
        ];
    }
}
