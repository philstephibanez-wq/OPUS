<?php
declare(strict_types=1);

use Opus\File\StructuredFileLoader;

final class OwasysFsmDiagramBuilder
{
    private const REVISION = 'P117W_R45B2A4Z';

    /**
     * Stable presentation order for the fixed OWASYS FSM.
     *
     * @var list<string>
     */
    private const LOGICAL_STATE_ORDER = [
        'login',
        'registry',
        'account',
        'creation',
        'data',
        'structure',
        'security',
        'workflows',
        'source',
        'build',
    ];

    /**
     * Readable classic FSM projection.
     *
     * This deliberately includes forward branches, backward returns and
     * representative self-loops so the rendering reads as a state machine,
     * not as an org-chart or linear workflow.
     *
     * Every tuple is verified against canonical config/fsm.json at runtime.
     *
     * @var list<array{0:string,1:string,2:string}>
     */
    private const LOGICAL_EDGES = [
        /* Entry and authentication branch. */
        ['login', 'login_success', 'registry'],
        ['login', 'login_failed', 'login'],
        ['login', 'password_change_required', 'account'],
        ['account', 'password_change_failed', 'account'],
        ['account', 'password_changed', 'registry'],

        /* Registry branch. */
        ['registry', 'registry_action_failed', 'registry'],
        ['registry', 'create_new_app', 'creation'],
        ['registry', 'select_app', 'data'],
        ['registry', 'open_account', 'account'],

        /* Creation flow and returns. */
        ['creation', 'application_creation_failed', 'creation'],
        ['creation', 'application_created', 'data'],
        ['creation', 'cancel_creation', 'registry'],

        /* Main application fan-out. */
        ['data', 'open_data', 'data'],
        ['data', 'open_structure', 'structure'],
        ['data', 'open_security', 'security'],
        ['data', 'open_workflows', 'workflows'],
        ['data', 'open_source', 'source'],
        ['data', 'open_build', 'build'],

        /* Representative state loops. */
        ['structure', 'open_structure', 'structure'],
        ['security', 'open_security', 'security'],
        ['workflows', 'open_workflows', 'workflows'],
        ['source', 'open_source', 'source'],
        ['build', 'open_build', 'build'],

        /* Long logical returns. */
        ['structure', 'change_app', 'registry'],
        ['security', 'change_app', 'registry'],
        ['workflows', 'change_app', 'registry'],
        ['source', 'change_app', 'registry'],
        ['build', 'change_app', 'registry'],

        /* Session exit is a real return to the beginning. */
        ['build', 'logout', 'login'],
    ];

    public function __construct(
        private readonly string $siteRoot,
        private readonly OwasysAuthSession $session
    ) {
    }

    /**
     * Fixed logical projection of the canonical OWASYS FSM.
     *
     * Contract:
     * - layout root is always canonical initial_state;
     * - state set and selected topology are identical on every page;
     * - current state changes highlight only;
     * - branches, returns and self-loops remain visible;
     * - no invented state, signal or transition;
     * - Menu = FSM remains the source of labels and actionable links;
     * - renderer stays native OPUS SVG and uses the OWASYS visual theme.
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
        $stateLabels = [];
        $menuSignalByTransition = [];

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
            $stateLabels[$id] = $label;

            foreach ((array) ($item['signals'] ?? []) as $signalItem) {
                if (!is_array($signalItem)) {
                    throw new RuntimeException(
                        'OWASYS_FSM_MENU_SIGNAL_INVALID:' . $id
                    );
                }

                $transitionId = trim((string) (
                    $signalItem['transition_id'] ?? ''
                ));

                if ($transitionId === '') {
                    throw new RuntimeException(
                        'OWASYS_FSM_MENU_SIGNAL_FIELDS_INVALID:' . $id
                    );
                }

                $menuSignalByTransition[$transitionId] = $signalItem;
            }
        }

        if (!isset($menuByState[$currentState])) {
            return $this->hidden();
        }

        $initialState = trim((string) ($fsm['initial_state'] ?? ''));
        if ($initialState === ''
            || !isset($statesById[$initialState])
            || !isset($menuByState[$initialState])) {
            throw new RuntimeException(
                'OWASYS_FSM_WORKFLOW_INITIAL_STATE_INVALID:'
                . $initialState
            );
        }

        if ($initialState !== self::LOGICAL_STATE_ORDER[0]) {
            throw new RuntimeException(
                'OWASYS_FSM_WORKFLOW_INITIAL_ORDER_DIVERGENCE:'
                . $initialState
            );
        }

        $stateOrder = [];
        foreach (self::LOGICAL_STATE_ORDER as $stateId) {
            if (isset($menuByState[$stateId])) {
                $stateOrder[] = $stateId;
            }
        }

        foreach (array_keys($menuByState) as $stateId) {
            if (!in_array($stateId, $stateOrder, true)) {
                throw new RuntimeException(
                    'OWASYS_FSM_WORKFLOW_STATE_UNMAPPED:'
                    . $stateId
                );
            }
        }

        $states = [];
        foreach ($stateOrder as $stateId) {
            $state = $statesById[$stateId] ?? null;
            if (!is_array($state)) {
                throw new RuntimeException(
                    'OWASYS_FSM_WORKFLOW_STATE_UNKNOWN:'
                    . $stateId
                );
            }
            $states[] = $state;
        }

        $canonicalTransitions = array_values(array_filter(
            (array) ($fsm['transitions'] ?? []),
            'is_array'
        ));

        $transitions = [];
        $transitionLabels = [];
        $transitionLinks = [];

        foreach (self::LOGICAL_EDGES as [$from, $signal, $to]) {
            if (!isset($menuByState[$from], $menuByState[$to])) {
                continue;
            }

            $transition = $this->canonicalTransition(
                $canonicalTransitions,
                $from,
                $signal,
                $to
            );

            if (!is_array($transition)) {
                throw new RuntimeException(
                    'OWASYS_FSM_WORKFLOW_EDGE_MISSING:'
                    . $from . ':' . $signal . ':' . $to
                );
            }

            $transitionId = trim((string) ($transition['id'] ?? ''));
            if ($transitionId === '') {
                throw new RuntimeException(
                    'OWASYS_FSM_WORKFLOW_TRANSITION_ID_MISSING:'
                    . $from . ':' . $signal . ':' . $to
                );
            }

            $projected = $menuSignalByTransition[$transitionId] ?? null;
            if (!is_array($projected)
                || (string) ($projected['signal'] ?? '') !== $signal
                || (string) ($projected['target'] ?? '') !== $to) {
                throw new RuntimeException(
                    'OWASYS_FSM_WORKFLOW_MENU_DIVERGENCE:'
                    . $transitionId
                );
            }

            $transitions[] = $transition;
            $transitionLabels[$transitionId] = $signal;

            if (($projected['actionable'] ?? false) === true) {
                $url = trim((string) ($projected['url'] ?? ''));
                if (!$this->isLocalUrl($url)) {
                    throw new RuntimeException(
                        'OWASYS_FSM_WORKFLOW_SIGNAL_URL_INVALID:'
                        . $transitionId
                    );
                }
                $transitionLinks[$transitionId] = $url;
            }
        }

        if (count($transitions) < 20) {
            throw new RuntimeException(
                'OWASYS_FSM_WORKFLOW_TOO_SPARSE:'
                . count($transitions)
            );
        }

        $definition = $fsm;
        $definition['name'] = 'OWASYS · FSM';
        $definition['states'] = $states;
        $definition['transitions'] = $transitions;
        $definition['initial_state'] = $initialState;

        $final = trim((string) ($definition['final_state'] ?? ''));
        if ($final !== '' && !isset($menuByState[$final])) {
            unset($definition['final_state']);
        }

        /*
         * Classic spaced renderer. The fixed root is ALWAYS initial_state.
         * currentState is separate and affects highlight only.
         */
        $diagram = \OPUS_FSM_Diagram::renderDefinition(
            $definition,
            $currentState,
            [],
            [],
            array_intersect_key(
                $stateLabels,
                array_fill_keys($stateOrder, true)
            ),
            $transitionLabels,
            false,
            $transitionLinks,
            $initialState
        );

        return [
            'visible' => true,
            'description' => 'FSM fixe · départ '
                . $stateLabels[$initialState]
                . ' · branches + retours + boucles réels · état courant '
                . $stateLabels[$currentState]
                . ' surligné uniquement',
            'html' => $diagram,
            'current_state' => $currentState,
            'current_label' => $stateLabels[$currentState],
            'projected_transition_count' => count($transitions),
        ];
    }

    /**
     * @param list<array<string,mixed>> $transitions
     * @return array<string,mixed>|null
     */
    private function canonicalTransition(
        array $transitions,
        string $from,
        string $signal,
        string $to
    ): ?array {
        $matches = [];

        foreach ($transitions as $transition) {
            $transitionFrom = trim((string) (
                $transition['from'] ?? ''
            ));
            $transitionSignal = trim((string) (
                $transition['signal'] ?? ''
            ));
            $transitionTo = trim((string) (
                $transition['next_state']
                ?? $transition['nextState']
                ?? ''
            ));
            $interrupt = trim((string) (
                $transition['interrupt'] ?? ''
            ));

            if ($transitionFrom === $from
                && $transitionSignal === $signal
                && $transitionTo === $to
                && $interrupt === '') {
                $matches[] = $transition;
            }
        }

        if (count($matches) > 1) {
            throw new RuntimeException(
                'OWASYS_FSM_WORKFLOW_EDGE_AMBIGUOUS:'
                . $from . ':' . $signal . ':' . $to
            );
        }

        return $matches[0] ?? null;
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
