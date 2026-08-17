<?php
declare(strict_types=1);

use Opus\File\StructuredFileLoader;

/** Builds the OWASYS menu as a projection of the canonical FSM. */
final class OwasysNavigationBuilder
{
    /** @var array<string,true> */
    private const SIGNAL_TYPES = [
        'navigation' => true,
        'command' => true,
        'outcome' => true,
        'system' => true,
    ];

    public function __construct(
        private readonly string $siteRoot,
        private readonly OwasysRuntimeSecurity $security
    ) {
    }

    /**
     * Contract A4AI:
     * - every canonical visible state is one menu entry;
     * - every state-local relation is a submenu signal, irrespective of type;
     * - ordinary global navigation is rendered once, never copied into every
     *   state submenu;
     * - cyan/actionable means a real current transition with a real GET route;
     * - command/outcome/system relations remain visible FSM facts but are not
     *   forged into GET links;
     * - ACL and current-application availability remain authoritative.
     *
     * @param array<string,mixed> $fsmConfig
     * @param array<string,mixed>|null $identity
     * @param callable(string):string $routeUrl
     * @return list<array<string,mixed>>
     */
    public function build(
        array $fsmConfig,
        ?array $identity,
        string $currentState,
        bool $hasCurrentApp,
        callable $routeUrl
    ): array {
        $signalRegistry = $this->signalRegistry($fsmConfig);
        $signalRoutes = $this->signalRoutes();
        $items = [];
        $itemsById = [];
        $statesById = [];
        $ordinal = 0;

        foreach ((array) ($fsmConfig['states'] ?? []) as $state) {
            ++$ordinal;
            if (!is_array($state)) {
                throw new RuntimeException('OWASYS_NAVIGATION_STATE_INVALID');
            }

            $stateId = trim((string) ($state['id'] ?? ''));
            if ($stateId === '' || $stateId === '*' || isset($statesById[$stateId])) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_STATE_INVALID:' . $stateId
                );
            }

            $stateType = trim((string) ($state['type'] ?? ''));
            if (!in_array(
                $stateType,
                ['screen', 'workflow', 'result', 'system'],
                true
            )) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_STATE_TYPE_INVALID:' . $stateId
                );
            }
            $module = trim((string) ($state['module'] ?? $stateId));
            $route = trim((string) ($state['route'] ?? ''));
            $navigation = is_array($state['navigation'] ?? null)
                ? $state['navigation']
                : [];
            $labelKey = trim((string) (
                $navigation['label']
                ?? $state['title_key']
                ?? ('menu.' . $module)
            ));
            if ($module === '' || $route === '' || $labelKey === '') {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_STATE_FIELDS_INVALID:' . $stateId
                );
            }

            $requiresAuth = ($state['requires_auth'] ?? false) === true;
            $requiresCurrentApp =
                ($state['requires_current_app'] ?? false) === true;
            $allowed = !$requiresAuth
                || $this->security->isAllowed($identity, $module, 'open');
            $available = $allowed
                && (!$requiresCurrentApp || $hasCurrentApp);

            $statesById[$stateId] = $state;
            $items[] = [
                'id' => $stateId,
                'state_type' => $stateType,
                'module' => $module,
                'label_key' => $labelKey,
                'label' => '',
                'visible' => ($navigation['visible'] ?? false) === true,
                'allowed' => $allowed,
                'available' => $available,
                'requires_current_app' => $requiresCurrentApp,
                'active' => $stateId === $currentState,
                'order' => (int) ($navigation['order'] ?? (1000 + $ordinal)),
                'signals' => [],
                'has_signals' => false,
                'global_signals' => [],
                'has_global_signals' => false,
                'global_host' => false,
            ];
        }

        usort(
            $items,
            static fn (array $left, array $right): int =>
                ($left['order'] <=> $right['order'])
                ?: strcmp((string) $left['id'], (string) $right['id'])
        );
        foreach ($items as $index => $item) {
            $itemsById[(string) $item['id']] = $index;
        }

        if (!isset($itemsById[$currentState])) {
            throw new RuntimeException(
                'OWASYS_NAVIGATION_CURRENT_STATE_UNKNOWN:' . $currentState
            );
        }

        $globalSignals = [];
        $transitionIds = [];

        foreach ((array) ($fsmConfig['transitions'] ?? []) as $transition) {
            if (!is_array($transition)) {
                throw new RuntimeException('OWASYS_NAVIGATION_TRANSITION_INVALID');
            }

            $transitionId = trim((string) ($transition['id'] ?? ''));
            $signal = trim((string) ($transition['signal'] ?? ''));
            $to = trim((string) (
                $transition['next_state']
                ?? $transition['nextState']
                ?? ''
            ));
            $interrupt = trim((string) ($transition['interrupt'] ?? ''));
            $scope = trim((string) ($transition['scope'] ?? ''));

            if ($transitionId === '' || $signal === '' || $to === '') {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_TRANSITION_FIELDS_INVALID'
                );
            }
            if (isset($transitionIds[$transitionId])) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_TRANSITION_DUPLICATE:' . $transitionId
                );
            }
            $transitionIds[$transitionId] = true;

            if (!isset($signalRegistry[$signal])) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_SIGNAL_UNDECLARED:' . $signal
                );
            }
            if (!isset($itemsById[$to])) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_TARGET_UNKNOWN:' . $to
                );
            }

            if ($interrupt === 'nmi') {
                continue;
            }
            if ($interrupt !== '') {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_INTERRUPT_INVALID:' . $interrupt
                );
            }

            if ($scope === 'global') {
                $sources = $this->globalSources($transition, $statesById);
                if (!in_array($currentState, $sources, true)) {
                    continue;
                }

                $definition = $signalRegistry[$signal];
                if (($definition['menu'] ?? false) !== true) {
                    continue;
                }
                if (($definition['type'] ?? '') !== 'navigation') {
                    throw new RuntimeException(
                        'OWASYS_NAVIGATION_GLOBAL_MENU_TYPE_INVALID:' . $signal
                    );
                }

                $target = $items[$itemsById[$to]];
                $mappedRoute = $signalRoutes[$signal] ?? null;
                $actionable = is_string($mappedRoute)
                    && $mappedRoute !== ''
                    && ($target['allowed'] ?? false) === true
                    && ($target['available'] ?? false) === true;
                $url = $actionable ? $routeUrl($mappedRoute) : '';

                $globalSignals[] = [
                    'transition_id' => $transitionId,
                    'signal' => $signal,
                    'signal_type' => 'navigation',
                    'target' => $to,
                    'target_label_key' => (string) ($target['label_key'] ?? ''),
                    'target_label' => '',
                    'url' => $url,
                    'actionable' => $actionable,
                    'has_route' => is_string($mappedRoute) && $mappedRoute !== '',
                    'active_source' => true,
                    'target_available' =>
                        ($target['allowed'] ?? false) === true
                        && ($target['available'] ?? false) === true,
                    'order' => (int) ($definition['menu_order'] ?? ($target['order'] ?? PHP_INT_MAX)),
                ];
                continue;
            }

            if ($scope !== '') {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_SCOPE_INVALID:' . $scope
                );
            }

            $from = trim((string) ($transition['from'] ?? ''));
            if ($from === '' || $from === '*' || !isset($itemsById[$from])) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_LOCAL_SOURCE_INVALID:' . $from
                );
            }

            $sourceIndex = $itemsById[$from];
            $target = $items[$itemsById[$to]];
            $definition = $signalRegistry[$signal];
            $mappedRoute = $signalRoutes[$signal] ?? null;
            $isNavigation = ($definition['type'] ?? '') === 'navigation';
            $actionable = $from === $currentState
                && $isNavigation
                && is_string($mappedRoute)
                && $mappedRoute !== ''
                && ($target['allowed'] ?? false) === true
                && ($target['available'] ?? false) === true;

            $items[$sourceIndex]['signals'][] = [
                'transition_id' => $transitionId,
                'signal' => $signal,
                'signal_type' => (string) ($definition['type'] ?? ''),
                'target' => $to,
                'target_label_key' => (string) ($target['label_key'] ?? ''),
                'target_label' => '',
                'url' => $actionable ? $routeUrl($mappedRoute) : '',
                'actionable' => $actionable,
                'has_route' => is_string($mappedRoute) && $mappedRoute !== '',
                'active_source' => $from === $currentState,
                'target_available' =>
                    ($target['allowed'] ?? false) === true
                    && ($target['available'] ?? false) === true,
                'order' => (int) ($target['order'] ?? PHP_INT_MAX),
            ];
        }

        usort(
            $globalSignals,
            static fn (array $left, array $right): int =>
                ($left['order'] <=> $right['order'])
                ?: strcmp((string) $left['signal'], (string) $right['signal'])
        );

        foreach ($items as $index => $item) {
            usort(
                $items[$index]['signals'],
                static fn (array $left, array $right): int =>
                    ($left['order'] <=> $right['order'])
                    ?: strcmp((string) $left['signal'], (string) $right['signal'])
            );
            $items[$index]['has_signals'] = $items[$index]['signals'] !== [];
        }

        /* Global rail is emitted exactly once, anchored to Applications. */
        $hostId = isset($itemsById['registry']) ? 'registry' : $currentState;
        $hostIndex = $itemsById[$hostId];
        $items[$hostIndex]['global_host'] = true;
        $items[$hostIndex]['global_signals'] = $globalSignals;
        $items[$hostIndex]['has_global_signals'] = $globalSignals !== [];

        return $items;
    }

    /**
     * @param array<string,mixed> $transition
     * @param array<string,array<string,mixed>> $statesById
     * @return list<string>
     */
    private function globalSources(array $transition, array $statesById): array
    {
        $sources = $transition['from_states'] ?? null;
        if (!is_array($sources) || $sources === []) {
            throw new RuntimeException(
                'OWASYS_NAVIGATION_GLOBAL_SOURCES_MISSING:'
                . (string) ($transition['signal'] ?? '')
            );
        }

        $result = [];
        foreach ($sources as $source) {
            $source = is_string($source) ? trim($source) : '';
            if ($source === '' || !isset($statesById[$source])) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_GLOBAL_SOURCE_INVALID:' . $source
                );
            }
            $result[$source] = true;
        }

        return array_keys($result);
    }

    /**
     * @param array<string,mixed> $fsmConfig
     * @return array<string,array{type:string,menu:bool}>
     */
    private function signalRegistry(array $fsmConfig): array
    {
        $registry = [];
        foreach ((array) ($fsmConfig['signals'] ?? []) as $definition) {
            if (!is_array($definition)) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_SIGNAL_REGISTRY_ENTRY_INVALID'
                );
            }
            $id = trim((string) ($definition['id'] ?? ''));
            $type = trim((string) ($definition['type'] ?? ''));
            $menu = ($definition['menu'] ?? false) === true;
            if ($id === '' || isset($registry[$id])) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_SIGNAL_REGISTRY_ID_INVALID:' . $id
                );
            }
            if (!isset(self::SIGNAL_TYPES[$type])) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_SIGNAL_TYPE_INVALID:' . $id . ':' . $type
                );
            }
            if ($menu && $type !== 'navigation') {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_SIGNAL_MENU_TYPE_INVALID:' . $id
                );
            }
            $registry[$id] = [
                'type' => $type,
                'menu' => $menu,
                'menu_order' => (int) ($definition['menu_order'] ?? PHP_INT_MAX),
            ];
        }
        if ($registry === []) {
            throw new RuntimeException('OWASYS_NAVIGATION_SIGNAL_REGISTRY_EMPTY');
        }
        return $registry;
    }

    /** @return array<string,string> signal => route */
    private function signalRoutes(): array
    {
        try {
            $routes = StructuredFileLoader::instance()->read(
                $this->siteRoot . '/config/routes.json'
            );
        } catch (Throwable $cause) {
            throw new RuntimeException(
                'OWASYS_NAVIGATION_SIGNAL_ROUTES_INVALID:'
                . $cause->getMessage(),
                0,
                $cause
            );
        }

        if (($routes['contract'] ?? null) !== 'OPUS_SIGNAL_ROUTES_V2') {
            throw new RuntimeException(
                'OWASYS_NAVIGATION_SIGNAL_ROUTES_CONTRACT_INVALID'
            );
        }

        $result = [];
        foreach (['system_routes', 'routes'] as $section) {
            foreach ((array) ($routes[$section] ?? []) as $route => $signal) {
                if (!is_string($route) || !is_string($signal)) {
                    throw new RuntimeException(
                        'OWASYS_NAVIGATION_SIGNAL_ROUTE_ENTRY_INVALID'
                    );
                }
                $route = trim($route);
                $signal = trim($signal);
                if ($route === '' || $signal === '') {
                    throw new RuntimeException(
                        'OWASYS_NAVIGATION_SIGNAL_ROUTE_ENTRY_EMPTY'
                    );
                }
                if (isset($result[$signal]) && $result[$signal] !== $route) {
                    throw new RuntimeException(
                        'OWASYS_NAVIGATION_SIGNAL_ROUTE_AMBIGUOUS:' . $signal
                    );
                }
                $result[$signal] = $route;
            }
        }
        return $result;
    }
}
