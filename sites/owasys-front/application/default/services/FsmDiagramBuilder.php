<?php
declare(strict_types=1);

use Opus\File\StructuredFileLoader;

final class OwasysFsmDiagramBuilder
{
    public function __construct(
        private readonly string $siteRoot,
        private readonly OwasysAuthSession $session
    ) {
    }

    /**
     * The diagram is the active-state graph of the exact FSM menu projection.
     * The menu owns no second state/route registry and neither does this class.
     *
     * @param array<string,mixed> $pageData
     * @return array{
     *   visible:bool,
     *   description:string,
     *   html:string,
     *   current_state:string,
     *   current_label:string,
     *   projected_transition_count:int
     * }
     */
    public function build(array $pageData): array
    {
        $identityView = is_array($pageData['identity'] ?? null)
            ? $pageData['identity']
            : [];
        $currentState = trim((string) ($pageData['fsm']['state'] ?? ''));

        if (($identityView['authenticated'] ?? false) !== true) {
            return $this->hidden();
        }

        $identity = $this->session->user();
        if (!is_array($identity)) {
            throw new RuntimeException(
                'OWASYS_FSM_NATIVE_IDENTITY_REQUIRED'
            );
        }

        $fsm = $this->loadFsm();
        $this->assertSignalRegistryComplete($fsm);
        $statesById = $this->statesById($fsm);

        $menuByState = [];
        $menuOrder = [];
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
                    'OWASYS_FSM_MENU_PROJECTION_INVALID:' . $id
                );
            }


            $menuByState[$id] = $item;
            $menuOrder[$id] = count($menuOrder);
            $stateLabels[$id] = $label;
        }

        if (!isset($menuByState[$currentState])) {
            return $this->hidden();
        }

        $currentMenu = $menuByState[$currentState];
        $transitionProjection = [];
        $relevantStates = [$currentState => true];

        foreach ((array) ($currentMenu['signals'] ?? []) as $signalItem) {
            if (!is_array($signalItem)) {
                throw new RuntimeException(
                    'OWASYS_FSM_MENU_SIGNAL_INVALID:' . $currentState
                );
            }

            $transitionId = trim((string) (
                $signalItem['transition_id'] ?? ''
            ));
            $target = trim((string) ($signalItem['target'] ?? ''));

            if ($transitionId === '' || $target === '') {
                throw new RuntimeException(
                    'OWASYS_FSM_MENU_SIGNAL_FIELDS_INVALID:'
                    . $currentState
                );
            }
            if (!isset($menuByState[$target])) {
                // The principal FSM diagram is the same projection as the
                // principal FSM menu. Signals to non-menu system states are
                // still canonical in config/profiler, but not invented here.
                continue;
            }
            if (isset($transitionProjection[$transitionId])) {
                throw new RuntimeException(
                    'OWASYS_FSM_MENU_TRANSITION_DUPLICATE:'
                    . $transitionId
                );
            }

            $transitionProjection[$transitionId] = $signalItem;
            $relevantStates[$target] = true;
        }

        $states = [];
        foreach ($menuByState as $id => $_item) {
            if (!isset($relevantStates[$id])) {
                continue;
            }
            $state = $statesById[$id] ?? null;
            if (!is_array($state)) {
                throw new RuntimeException(
                    'OWASYS_FSM_MENU_STATE_UNKNOWN:' . $id
                );
            }
            $states[] = $state;
        }

        $transitions = [];
        $transitionLabels = [];
        $transitionLinks = [];

        foreach ((array) ($fsm['transitions'] ?? []) as $transition) {
            if (!is_array($transition)) {
                continue;
            }

            $transitionId = trim((string) ($transition['id'] ?? ''));
            if ($transitionId === ''
                || !isset($transitionProjection[$transitionId])) {
                continue;
            }

            $from = trim((string) ($transition['from'] ?? ''));
            $to = trim((string) (
                $transition['next_state']
                ?? $transition['nextState']
                ?? ''
            ));
            $signal = trim((string) ($transition['signal'] ?? ''));
            $interrupt = trim((string) ($transition['interrupt'] ?? ''));

            if ($interrupt === 'nmi') {
                continue;
            }
            if ($interrupt !== '' || $from === '*') {
                throw new RuntimeException(
                    'OWASYS_FSM_MENU_TRANSITION_SEMANTICS_INVALID:'
                    . $transitionId
                );
            }
            if ($from !== $currentState || !isset($relevantStates[$to])) {
                throw new RuntimeException(
                    'OWASYS_FSM_MENU_DIAGRAM_DIVERGENCE:'
                    . $transitionId
                );
            }

            $projected = $transitionProjection[$transitionId];
            if ((string) ($projected['signal'] ?? '') !== $signal
                || (string) ($projected['target'] ?? '') !== $to) {
                throw new RuntimeException(
                    'OWASYS_FSM_MENU_SIGNAL_DIVERGENCE:'
                    . $transitionId
                );
            }

            $transitions[] = $transition;
            $transitionLabels[$transitionId] = $signal;

            $url = trim((string) ($projected['url'] ?? ''));
            if (($projected['actionable'] ?? false) === true) {
                if (!$this->isLocalUrl($url)) {
                    throw new RuntimeException(
                        'OWASYS_FSM_MENU_SIGNAL_URL_INVALID:'
                        . $transitionId
                    );
                }
                $transitionLinks[$transitionId] = $url;
            }
        }

        $definition = $fsm;
        $definition['name'] = 'OWASYS · FSM';
        $definition['states'] = $states;
        $definition['transitions'] = $transitions;

        $initial = trim((string) ($definition['initial_state'] ?? ''));
        if (!isset($relevantStates[$initial])) {
            $definition['initial_state'] = '';
        }

        $final = trim((string) ($definition['final_state'] ?? ''));
        if ($final !== '' && !isset($relevantStates[$final])) {
            unset($definition['final_state']);
        }

        $diagram = \OPUS_FSM_Diagram::renderDefinition(
            $definition,
            $currentState,
            [],
            [],
            array_intersect_key($stateLabels, $relevantStates),
            $transitionLabels,
            true,
            $transitionLinks,
            $currentState
        );

        return [
            'visible' => true,
            'description' => 'Menu = FSM · '
                . $stateLabels[$currentState]
                . ' · '
                . count($transitions)
                . ' signaux sortants',
            'html' => $diagram,
            'current_state' => $currentState,
            'current_label' => $stateLabels[$currentState],
            'projected_transition_count' => count($transitions),
        ];
    }

    /** @param array<string,mixed> $fsm */
    private function assertSignalRegistryComplete(array $fsm): void
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
     *   current_state:string,
     *   current_label:string,
     *   projected_transition_count:int
     * }
     */
    private function hidden(): array
    {
        return [
            'visible' => false,
            'description' => '',
            'html' => '',
            'current_state' => '',
            'current_label' => '',
            'projected_transition_count' => 0,
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