<?php
declare(strict_types=1);

use Opus\File\StructuredFileLoader;

final class OwasysFsmDiagramBuilder
{
    public function __construct(
        private readonly string $siteRoot,
        private readonly OwasysAuthSession $session,
        private readonly OwasysRuntimeSecurity $security
    ) {
    }

    /**
     * Builds the graphical navigation from the exact normalized navigation
     * projection already consumed by navigation.score.
     *
     * @param array<string,mixed> $pageData
     * @return array{
     *   visible:bool,
     *   description:string,
     *   html:string,
     *   signal_count:int,
     *   projected_signal_count:int,
     *   signals:list<array{id:string,projected:bool}>
     * }
     */
    public function build(array $pageData): array
    {
        $identityView = is_array($pageData['identity'] ?? null)
            ? $pageData['identity']
            : [];
        $currentState = trim((string) ($pageData['fsm']['state'] ?? ''));

        if (($identityView['authenticated'] ?? false) !== true
            || in_array($currentState, ['login', 'account'], true)) {
            return $this->hidden();
        }

        $identity = $this->session->user();
        if (!is_array($identity)) {
            throw new RuntimeException(
                'OWASYS_FSM_NATIVE_IDENTITY_REQUIRED'
            );
        }

        $fsm = $this->loadFsm();
        $signalCoverage = $this->assertSignalRegistryComplete($fsm);
        $statesById = $this->statesById($fsm);
        $states = [];
        $visible = [];
        $labels = [];
        $stateLinks = [];
        $stateLabels = [];

        foreach ((array) ($pageData['navigation'] ?? []) as $item) {
            if (!is_array($item)
                || ($item['allowed'] ?? false) !== true) {
                continue;
            }

            $id = trim((string) ($item['id'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
            $state = $statesById[$id] ?? null;

            if ($id === '' || $label === '' || !is_array($state)) {
                throw new RuntimeException(
                    'OWASYS_FSM_NAVIGATION_PROJECTION_INVALID:' . $id
                );
            }

            $module = trim((string) ($state['module'] ?? $id));
            if ($module === ''
                || !$this->security->isAllowed(
                    $identity,
                    $module,
                    'open'
                )) {
                throw new RuntimeException(
                    'OWASYS_FSM_NAVIGATION_ACL_DIVERGENCE:' . $id
                );
            }

            $states[] = $state;
            $visible[$id] = true;
            $labels[$id] = $label;
            $stateLabels[$id] = $label;

            $url = trim((string) ($item['url'] ?? ''));
            if (($item['available'] ?? false) === true
                && $this->isLocalUrl($url)) {
                $stateLinks[$id] = $url;
            }
        }

        if ($states === []) {
            return $this->hidden();
        }

        $transitions = [];
        $transitionLabels = [];
        $projectedSignals = [];
        foreach ((array) ($fsm['transitions'] ?? []) as $transition) {
            if (!is_array($transition)) {
                continue;
            }

            $from = trim((string) (
                $transition['from']
                ?? $transition['state']
                ?? ''
            ));
            $to = trim((string) (
                $transition['next_state']
                ?? $transition['nextState']
                ?? ''
            ));
            $signal = trim((string) ($transition['signal'] ?? ''));

            if ($signal === '') {
                throw new RuntimeException(
                    'OWASYS_FSM_NAVIGATION_SIGNAL_MISSING'
                );
            }
            if ($to === '' || !isset($visible[$to])) {
                continue;
            }
            if ($from !== '*' && !isset($visible[$from])) {
                continue;
            }

            // This card is the navigation projection, not an exhaustive dump
            // of a state's internal workflow. State-preserving transitions
            // remain canonical in config/fsm.json and in the Profiler.
            if ($from === $to) {
                continue;
            }

            $transitionId = trim((string) ($transition['id'] ?? ''));
            if ($transitionId === '') {
                throw new RuntimeException(
                    'OWASYS_FSM_NAVIGATION_TRANSITION_ID_MISSING'
                );
            }

            $transitions[] = $transition;
            $transitionLabels[$transitionId] = $signal;
            $projectedSignals[$signal] = true;
        }

        $definition = $fsm;
        $definition['name'] = 'OWASYS · FSM';
        $definition['states'] = $states;
        $definition['transitions'] = $transitions;

        $initial = trim((string) ($definition['initial_state'] ?? ''));
        if ($initial === '' || !isset($visible[$initial])) {
            // Never invent an initial state for a partial navigation
            // projection. Current state highlighting is independent.
            $definition['initial_state'] = '';
        }

        $final = trim((string) ($definition['final_state'] ?? ''));
        if ($final !== '' && !isset($visible[$final])) {
            unset($definition['final_state']);
        }

        $signalItems = [];
        foreach ($signalCoverage['ids'] as $signalId) {
            $signalItems[] = [
                'id' => $signalId,
                'projected' => isset($projectedSignals[$signalId]),
            ];
        }

        $diagram = \OPUS_FSM_Diagram::renderDefinition(
            $definition,
            isset($visible[$currentState]) ? $currentState : '',
            [],
            $stateLinks,
            $stateLabels,
            $transitionLabels,
            true
        );

        return [
            'visible' => true,
            'description' => 'OPUS_FSM_Diagram · '
                . (string) ($fsm['contract'] ?? 'FSM')
                . ' · Σ '
                . $signalCoverage['declared']
                . '/'
                . $signalCoverage['referenced']
                . ' · SVG '
                . count($projectedSignals),
            'html' => $diagram,
            'signal_count' => $signalCoverage['declared'],
            'projected_signal_count' => count($projectedSignals),
            'signals' => $signalItems,
        ];
    }

    /**
     * @param array<string,mixed> $fsm
     * @return array{declared:int,referenced:int,ids:list<string>}
     */
    private function assertSignalRegistryComplete(array $fsm): array
    {
        $declared = [];
        foreach ((array) ($fsm['signals'] ?? []) as $signal) {
            if (!is_array($signal)) {
                throw new RuntimeException(
                    'OWASYS_FSM_SIGNAL_REGISTRY_ENTRY_INVALID'
                );
            }
            $id = trim((string) ($signal['id'] ?? ''));
            if ($id === '' || isset($declared[$id])) {
                throw new RuntimeException(
                    'OWASYS_FSM_SIGNAL_REGISTRY_ID_INVALID:' . $id
                );
            }
            $declared[$id] = true;
        }

        $referenced = [];
        foreach ((array) ($fsm['transitions'] ?? []) as $transition) {
            if (!is_array($transition)) {
                throw new RuntimeException(
                    'OWASYS_FSM_TRANSITION_ENTRY_INVALID'
                );
            }
            $signal = trim((string) ($transition['signal'] ?? ''));
            if ($signal === '' || !isset($declared[$signal])) {
                throw new RuntimeException(
                    'OWASYS_FSM_SIGNAL_UNDECLARED:' . $signal
                );
            }
            $referenced[$signal] = true;
        }

        $unused = array_diff_key($declared, $referenced);
        if ($unused !== []) {
            throw new RuntimeException(
                'OWASYS_FSM_SIGNAL_UNUSED:'
                . implode(',', array_keys($unused))
            );
        }

        return [
            'declared' => count($declared),
            'referenced' => count($referenced),
            'ids' => array_keys($declared),
        ];
    }

    /**
     * @param array<string,mixed> $fsm
     * @return array<string,array<string,mixed>>
     */
    private function statesById(array $fsm): array
    {
        $states = [];

        foreach ((array) ($fsm['states'] ?? []) as $state) {
            if (!is_array($state)) {
                continue;
            }

            $id = trim((string) ($state['id'] ?? ''));
            if ($id !== '') {
                $states[$id] = $state;
            }
        }

        return $states;
    }

    private function isLocalUrl(string $url): bool
    {
        return $url !== ''
            && $url[0] === '/'
            && !str_contains($url, "\0");
    }

    /**
     * @return array{
     *   visible:bool,
     *   description:string,
     *   html:string,
     *   signal_count:int,
     *   projected_signal_count:int,
     *   signals:list<array{id:string,projected:bool}>
     * }
     */
    private function hidden(): array
    {
        return [
            'visible' => false,
            'description' => '',
            'html' => '',
            'signal_count' => 0,
            'projected_signal_count' => 0,
            'signals' => [],
        ];
    }

    /** @return array<string,mixed> */
    private function loadFsm(): array
    {
        $loader = StructuredFileLoader::instance();
        try {
            $site = $loader->read(
                $this->siteRoot . '/config/site.json'
            );
        } catch (Throwable $cause) {
            throw new RuntimeException(
                'OWASYS_FSM_NATIVE_SITE_CONFIG_INVALID:'
                . $cause->getMessage(),
                0,
                $cause
            );
        }

        $navigation = is_array($site['navigation'] ?? null)
            ? $site['navigation']
            : [];
        $relative = trim(
            str_replace(
                '\\',
                '/',
                (string) ($navigation['fsm'] ?? '')
            ),
            '/'
        );

        if ($relative === '' || str_contains($relative, '..')) {
            throw new RuntimeException(
                'OWASYS_FSM_NATIVE_CONFIG_PATH_INVALID'
            );
        }

        try {
            $fsm = $loader->read($this->siteRoot . '/' . $relative);
        } catch (Throwable $cause) {
            throw new RuntimeException(
                'OWASYS_FSM_NATIVE_CONFIG_INVALID:'
                . $cause->getMessage(),
                0,
                $cause
            );
        }

        if (($fsm['contract'] ?? null) !== 'OWASYS_NAVIGATION_FSM_V1') {
            throw new RuntimeException(
                'OWASYS_FSM_NATIVE_CONTRACT_INVALID'
            );
        }

        return $fsm;
    }
}