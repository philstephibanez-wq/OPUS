<?php
declare(strict_types=1);

use Opus\File\Json;

/** Read-only projection of one selected application's named EFSM through secured REST. */
final class OwasysApplicationFsmModel
{
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
    public function snapshot(
        string $siteId,
        array $actor,
        string $efsmId = 'navigation'
    ): array {
        $siteId = strtolower(trim($siteId));
        $efsmId = strtolower(trim($efsmId));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $siteId) !== 1
            || preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $efsmId) !== 1) {
            throw new RuntimeException('OWASYS_APPLICATION_EFSM_ID_INVALID');
        }

        $siteFile = $this->source->read($siteId, 'config/site.json', $actor);
        $site = Json::instance()->parse(
            (string) ($siteFile['content'] ?? ''),
            'config/site.json'
        );
        if ((string) ($site['site_id'] ?? '') !== $siteId) {
            throw new RuntimeException('OWASYS_APPLICATION_EFSM_SITE_MISMATCH');
        }

        $registry = is_array($site['efsms'] ?? null)
            ? $site['efsms']
            : [];
        $sourcePath = trim(str_replace(
            '\\',
            '/',
            (string) ($registry[$efsmId] ?? '')
        ), '/');

        if ($sourcePath === '' && $efsmId === 'navigation') {
            $sourcePath = isset(self::SYSTEM_APPLICATIONS[$siteId])
                ? 'config/fsm.json'
                : trim(str_replace(
                    '\\',
                    '/',
                    (string) (
                        $site['application_fsm']
                        ?? $site['navigation']['fsm']
                        ?? 'config/application.fsm.json'
                    )
                ), '/');
        }
        if ($sourcePath === ''
            || str_contains($sourcePath, '..')
            || str_starts_with($sourcePath, '/')) {
            throw new RuntimeException(
                'OWASYS_APPLICATION_EFSM_SOURCE_UNRESOLVED:' . $efsmId
            );
        }

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
                'OPUS_SECURITY_FSM_V1',
                'OWASYS_NAVIGATION_FSM_V1',
                'OWASYS_BACK_FSM_V1',
            ],
            true
        )) {
            throw new RuntimeException('OWASYS_APPLICATION_EFSM_CONTRACT_INVALID');
        }
        $declaredEfsm = trim((string) ($definition['efsm_id'] ?? ''));
        if ($declaredEfsm !== '' && $declaredEfsm !== $efsmId) {
            throw new RuntimeException('OWASYS_APPLICATION_EFSM_KIND_MISMATCH');
        }

        $states = is_array($definition['states'] ?? null)
            ? $definition['states']
            : [];
        $transitions = is_array($definition['transitions'] ?? null)
            ? $definition['transitions']
            : [];
        if ($states === []) {
            throw new RuntimeException('OWASYS_APPLICATION_EFSM_STATES_EMPTY');
        }

        $stateIds = [];
        foreach ($states as $state) {
            if (!is_array($state)) {
                throw new RuntimeException('OWASYS_APPLICATION_EFSM_STATE_INVALID');
            }
            $id = trim((string) ($state['id'] ?? ''));
            if ($id === '' || isset($stateIds[$id])) {
                throw new RuntimeException('OWASYS_APPLICATION_EFSM_STATE_ID_INVALID');
            }
            $stateIds[$id] = true;
        }

        $initial = trim((string) ($definition['initial_state'] ?? ''));
        if ($initial === '' || !isset($stateIds[$initial])) {
            throw new RuntimeException('OWASYS_APPLICATION_EFSM_INITIAL_STATE_INVALID');
        }
        foreach ($transitions as $transition) {
            if (!is_array($transition)) {
                throw new RuntimeException('OWASYS_APPLICATION_EFSM_TRANSITION_INVALID');
            }
            $from = trim((string) ($transition['from'] ?? ''));
            $to = trim((string) (
                $transition['next_state']
                ?? $transition['nextState']
                ?? ''
            ));
            $signal = trim((string) ($transition['signal'] ?? ''));
            if ($signal === '' || $to === '' || !isset($stateIds[$to])) {
                throw new RuntimeException('OWASYS_APPLICATION_EFSM_TRANSITION_INVALID');
            }
            if (($transition['scope'] ?? null) === 'global') {
                $sources = is_array($transition['from_states'] ?? null)
                    ? $transition['from_states']
                    : [];
                if ($sources === []) {
                    throw new RuntimeException(
                        'OWASYS_APPLICATION_EFSM_GLOBAL_SOURCES_EMPTY'
                    );
                }
                foreach ($sources as $source) {
                    if (!is_string($source) || !isset($stateIds[$source])) {
                        throw new RuntimeException(
                            'OWASYS_APPLICATION_EFSM_TRANSITION_SOURCE_INVALID'
                        );
                    }
                }
            } elseif ($from !== '*' && !isset($stateIds[$from])) {
                throw new RuntimeException(
                    'OWASYS_APPLICATION_EFSM_TRANSITION_SOURCE_INVALID'
                );
            }
        }

        $declaredSite = trim((string) ($definition['site_id'] ?? ''));
        if ($declaredSite !== '' && $declaredSite !== $siteId) {
            throw new RuntimeException('OWASYS_APPLICATION_EFSM_SITE_MISMATCH');
        }

        $hash = strtolower(trim((string) ($file['sha256'] ?? '')));
        if (preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
            throw new RuntimeException('OWASYS_APPLICATION_EFSM_HASH_INVALID');
        }

        return [
            'contract' => 'OWASYS_APPLICATION_EFSM_SNAPSHOT_V2',
            'application_id' => $siteId,
            'efsm_id' => $efsmId,
            'source_path' => $sourcePath,
            'sha256' => $hash,
            'state_count' => count($states),
            'transition_count' => count($transitions),
            'definition' => $definition,
            'diagram' => \OPUS_FSM_Diagram::renderDefinition(
                fsm: $definition,
                persistLayout: false
            ),
        ];
    }
}