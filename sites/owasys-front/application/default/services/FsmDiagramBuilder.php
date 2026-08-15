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
     *   signals:list<array{id:string,projected:bool}>,
     *   current_state:string,
     *   current_label:string,
     *   has_controls:bool,
     *   controls:list<array{
     *     signal:string,
     *     target:string,
     *     target_label:string,
     *     url:string,
     *     order:int
     *   }>
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
        $visibleOrder = [];
        $transitionLinks = [];
        $controls = [];

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
            $visibleOrder[$id] = count($visibleOrder);
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

        $routeSignals = $this->routeSignals();
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
            $interrupt = trim((string) ($transition['interrupt'] ?? ''));
            if ($interrupt === 'nmi') {
                // NMI is an out-of-band interrupt, never a navigation state
                // nor a user-selectable principal-navigation signal.
                continue;
            }
            if ($from === '*') {
                throw new RuntimeException(
                    'OWASYS_FSM_NAVIGATION_GLOBAL_SOURCE_FORBIDDEN:'
                    . $signal
                );
            }
            if ($to === ''
                || !isset($visible[$to])
                || !isset($visible[$from])) {
                continue;
            }

            // A FSM UI exposes the signals enabled by the current state.
            // Arrival states remain passive; the signal is the control.
            if ($from !== $currentState || $from === $to) {
                continue;
            }
            if (!isset($routeSignals[$signal])) {
                // Non-navigation signals remain canonical and auditable in
                // the 44/44 inventory and Profiler, but are not GET controls.
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

            if (isset($routeSignals[$signal])
                && isset($stateLinks[$to])) {
                $transitionLinks[$transitionId] = $stateLinks[$to];
                $controls[] = [
                    'signal' => $signal,
                    'target' => $to,
                    'target_label' => $labels[$to],
                    'url' => $stateLinks[$to],
                    'order' => $visibleOrder[$to] ?? PHP_INT_MAX,
                ];
            }
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

        usort(
            $controls,
            static fn (array $left, array $right): int =>
                ($left['order'] ?? PHP_INT_MAX)
                <=> ($right['order'] ?? PHP_INT_MAX)
        );

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
            [],
            $stateLabels,
            $transitionLabels,
            true,
            $transitionLinks
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
            'current_state' => $currentState,
            'current_label' => $labels[$currentState] ?? $currentState,
            'has_controls' => $controls !== [],
            'controls' => $controls,
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


    /** @return array<string,true> */
    private function routeSignals(): array
    {
        $loader = StructuredFileLoader::instance();

        try {
            $routes = $loader->read(
                $this->siteRoot . '/config/routes.json'
            );
        } catch (Throwable $cause) {
            throw new RuntimeException(
                'OWASYS_FSM_SIGNAL_ROUTES_INVALID:'
                . $cause->getMessage(),
                0,
                $cause
            );
        }

        if (($routes['contract'] ?? null) !== 'OPUS_SIGNAL_ROUTES_V2') {
            throw new RuntimeException(
                'OWASYS_FSM_SIGNAL_ROUTES_CONTRACT_INVALID'
            );
        }

        $signals = [];
        foreach (['system_routes', 'routes'] as $section) {
            foreach ((array) ($routes[$section] ?? []) as $signal) {
                if (!is_string($signal)) {
                    throw new RuntimeException(
                        'OWASYS_FSM_SIGNAL_ROUTE_ENTRY_INVALID'
                    );
                }

                $signal = trim($signal);
                if ($signal === '') {
                    throw new RuntimeException(
                        'OWASYS_FSM_SIGNAL_ROUTE_EMPTY'
                    );
                }
                $signals[$signal] = true;
            }
        }

        return $signals;
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
     *   signals:list<array{id:string,projected:bool}>,
     *   current_state:string,
     *   current_label:string,
     *   has_controls:bool,
     *   controls:list<array{
     *     signal:string,
     *     target:string,
     *     target_label:string,
     *     url:string,
     *     order:int
     *   }>
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
            'current_state' => '',
            'current_label' => '',
            'has_controls' => false,
            'controls' => [],
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