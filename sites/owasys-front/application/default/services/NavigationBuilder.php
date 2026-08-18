<?php
declare(strict_types=1);

use Opus\File\StructuredFileLoader;
use Opus\Fsm\FsmProcessor;

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

    /** @var array<string,true> */
    private const SIGNAL_ORIGINS = [
        'user' => true,
        'automatic' => true,
    ];

    public function __construct(
        private readonly string $siteRoot,
        private readonly OwasysRuntimeSecurity $security
    ) {
    }

    /**
     * Contract A4AZ:
     * - Menu = FSM remains a projection of the canonical FSM;
     * - actionability consumes FsmProcessor::inspectTransition(), therefore
     *   the same declared guards govern runtime execution and UI availability;
     * - ACL and target-state availability remain mandatory additional access
     *   constraints until they are migrated to canonical FSM guards;
     * - global transitions applicable to the current state are hosted under
     *   that current state instead of under Applications;
     * - a pure navigation self-loop remains a visible FSM fact but is not
     *   offered as a useful menu action;
     * - HTTP transport is independent from FSM origin/type/actionability.
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
        $bindings = $this->requestBindings();
        $signalRoutes = $bindings['get'];
        $signalPostActions = $bindings['post'];
        $processor = new FsmProcessor($fsmConfig);
        $fsmContext = $this->fsmContext($identity, $hasCurrentApp);

        foreach ($signalPostActions as $boundSignal => $binding) {
            if (!isset($signalRegistry[$boundSignal])) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_POST_SIGNAL_UNDECLARED:' . $boundSignal
                );
            }
            if (($signalRegistry[$boundSignal]['type'] ?? '') !== 'command') {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_POST_SIGNAL_TYPE_INVALID:' . $boundSignal
                );
            }
        }

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
                $targetAvailable = ($target['allowed'] ?? false) === true
                    && ($target['available'] ?? false) === true;
                $inspection = $processor->inspectTransition(
                    $currentState,
                    $signal,
                    $fsmContext
                );
                $fsmEnabled = $this->inspectionEnables(
                    $inspection,
                    $transitionId
                );
                $pureSelfLoop = $this->isPureNavigationSelfLoop(
                    $transition,
                    $definition,
                    $currentState,
                    $to
                );
                $actionable = is_string($mappedRoute)
                    && $mappedRoute !== ''
                    && $targetAvailable
                    && $fsmEnabled
                    && !$pureSelfLoop;
                $url = $actionable ? $routeUrl($mappedRoute) : '';

                $globalSignals[] = $this->signalView(
                    $transitionId,
                    $signal,
                    $definition,
                    $to,
                    $target,
                    $url,
                    $actionable,
                    $actionable,
                    false,
                    '',
                    '',
                    is_string($mappedRoute) && $mappedRoute !== '',
                    true,
                    $targetAvailable,
                    (int) ($definition['menu_order']
                        ?? ($target['order'] ?? PHP_INT_MAX)),
                    $inspection,
                    $pureSelfLoop ? 'current_state' : ''
                );
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
            $postBinding = $signalPostActions[$signal] ?? null;
            $isNavigation = ($definition['type'] ?? '') === 'navigation';
            $isCommand = ($definition['type'] ?? '') === 'command';
            $sourceRoute = trim((string) ($statesById[$from]['route'] ?? ''));
            $targetAvailable = ($target['allowed'] ?? false) === true
                && ($target['available'] ?? false) === true;

            $inspection = null;
            $fsmEnabled = false;
            if ($from === $currentState) {
                $inspection = $processor->inspectTransition(
                    $currentState,
                    $signal,
                    $fsmContext
                );
                $fsmEnabled = $this->inspectionEnables(
                    $inspection,
                    $transitionId
                );
            }

            $getActionable = $from === $currentState
                && $isNavigation
                && is_string($mappedRoute)
                && $mappedRoute !== ''
                && $targetAvailable
                && $fsmEnabled;

            if ($isCommand
                && is_array($postBinding)
                && (string) ($postBinding['route'] ?? '') !== $sourceRoute) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_POST_SOURCE_ROUTE_MISMATCH:' . $signal
                );
            }

            $postActionable = $from === $currentState
                && $isCommand
                && is_array($postBinding)
                && $targetAvailable
                && $fsmEnabled;

            $menuActionable = $getActionable || $postActionable;
            $url = $getActionable
                ? $routeUrl((string) $mappedRoute)
                : ($postActionable ? $routeUrl($sourceRoute) : '');

            $items[$sourceIndex]['signals'][] = $this->signalView(
                $transitionId,
                $signal,
                $definition,
                $to,
                $target,
                $url,
                $getActionable,
                $menuActionable,
                $postActionable,
                $postActionable ? 'owasys_action' : '',
                $postActionable ? (string) ($postBinding['action'] ?? '') : '',
                is_string($mappedRoute) && $mappedRoute !== '',
                $from === $currentState,
                $targetAvailable,
                (int) ($target['order'] ?? PHP_INT_MAX),
                $inspection,
                ''
            );
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

        /* Global rails are current-state transitions and belong to that state. */
        $hostIndex = $itemsById[$currentState];
        $items[$hostIndex]['global_host'] = true;
        $items[$hostIndex]['global_signals'] = $globalSignals;
        $items[$hostIndex]['has_global_signals'] = $globalSignals !== [];

        return $items;
    }

    /**
     * @param array<string,mixed>|null $identity
     * @return array<string,mixed>
     */
    private function fsmContext(?array $identity, bool $hasCurrentApp): array
    {
        return [
            'identity' => $identity,
            'is_authenticated' => is_array($identity),
            'roles' => is_array($identity['roles'] ?? null)
                ? array_values(array_filter($identity['roles'], 'is_string'))
                : [],
            'has_current_app' => $hasCurrentApp,
            'current_app' => $hasCurrentApp ? ['present' => true] : null,
        ];
    }

    /** @param array<string,mixed> $inspection */
    private function inspectionEnables(
        array $inspection,
        string $transitionId
    ): bool {
        return ($inspection['transition_found'] ?? false) === true
            && ($inspection['enabled'] ?? false) === true
            && (string) ($inspection['transition_id'] ?? '') === $transitionId;
    }

    /**
     * @param array<string,mixed> $transition
     * @param array<string,mixed> $definition
     */
    private function isPureNavigationSelfLoop(
        array $transition,
        array $definition,
        string $currentState,
        string $targetState
    ): bool {
        if (($definition['type'] ?? '') !== 'navigation'
            || $currentState !== $targetState) {
            return false;
        }

        $actions = $transition['actions'] ?? ($transition['action'] ?? []);
        $operations = $transition['runtime_operations'] ?? [];
        $hasActions = is_string($actions)
            ? trim($actions) !== ''
            : (is_array($actions) && $actions !== []);
        $hasOperations = is_array($operations) && $operations !== [];

        return !$hasActions && !$hasOperations;
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $target
     * @param array<string,mixed>|null $inspection
     * @return array<string,mixed>
     */
    private function signalView(
        string $transitionId,
        string $signal,
        array $definition,
        string $targetId,
        array $target,
        string $url,
        bool $getActionable,
        bool $menuActionable,
        bool $postActionable,
        string $requestField,
        string $requestValue,
        bool $hasRoute,
        bool $activeSource,
        bool $targetAvailable,
        int $order,
        ?array $inspection,
        string $projectionReason
    ): array {
        $reason = $projectionReason;
        $failedGuards = [];
        $fsmEnabled = false;

        if (is_array($inspection)) {
            $fsmEnabled = ($inspection['enabled'] ?? false) === true;
            $failedGuards = array_values(array_filter(
                is_array($inspection['failed_guards'] ?? null)
                    ? $inspection['failed_guards']
                    : [],
                'is_string'
            ));
            if ($reason === '') {
                $reason = trim((string) ($inspection['reason'] ?? ''));
            }
        }

        return [
            'transition_id' => $transitionId,
            'signal' => $signal,
            'signal_type' => (string) ($definition['type'] ?? ''),
            'signal_origin' => (string) ($definition['origin'] ?? ''),
            'target' => $targetId,
            'target_label_key' => (string) ($target['label_key'] ?? ''),
            'target_label' => '',
            'url' => $url,
            'actionable' => $getActionable,
            'menu_actionable' => $menuActionable,
            'is_get' => $getActionable,
            'is_post' => $postActionable,
            'request_field' => $requestField,
            'request_value' => $requestValue,
            'has_route' => $hasRoute,
            'active_source' => $activeSource,
            'target_available' => $targetAvailable,
            'fsm_enabled' => $fsmEnabled,
            'fsm_reason' => $reason,
            'failed_guards' => $failedGuards,
            'order' => $order,
        ];
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
     * @return array<string,array{type:string,origin:string,menu:bool,menu_order:int}>
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
            $origin = trim((string) ($definition['origin'] ?? ''));
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
            if (!isset(self::SIGNAL_ORIGINS[$origin])) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_SIGNAL_ORIGIN_INVALID:'
                    . $id . ':' . $origin
                );
            }
            if ($menu && $type !== 'navigation') {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_SIGNAL_MENU_TYPE_INVALID:' . $id
                );
            }
            $registry[$id] = [
                'type' => $type,
                'origin' => $origin,
                'menu' => $menu,
                'menu_order' => (int) ($definition['menu_order'] ?? PHP_INT_MAX),
            ];
        }
        if ($registry === []) {
            throw new RuntimeException('OWASYS_NAVIGATION_SIGNAL_REGISTRY_EMPTY');
        }
        return $registry;
    }

    /**
     * @return array{
     *   get:array<string,string>,
     *   post:array<string,array{route:string,action:string}>
     * }
     */
    private function requestBindings(): array
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

        $get = [];
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
                if (isset($get[$signal]) && $get[$signal] !== $route) {
                    throw new RuntimeException(
                        'OWASYS_NAVIGATION_SIGNAL_ROUTE_AMBIGUOUS:' . $signal
                    );
                }
                $get[$signal] = $route;
            }
        }

        $knownRoutes = array_fill_keys(array_merge(
            array_keys((array) ($routes['system_routes'] ?? [])),
            array_keys((array) ($routes['routes'] ?? []))
        ), true);
        $post = [];
        foreach ((array) ($routes['post_actions'] ?? []) as $route => $actions) {
            if (!is_string($route) || !is_array($actions)) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_POST_ROUTE_ENTRY_INVALID'
                );
            }
            $route = trim($route);
            if ($route === '' || !isset($knownRoutes[$route])) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_POST_ROUTE_UNKNOWN:' . $route
                );
            }
            foreach ($actions as $action => $signal) {
                if (!is_string($action) || !is_string($signal)) {
                    throw new RuntimeException(
                        'OWASYS_NAVIGATION_POST_ACTION_ENTRY_INVALID'
                    );
                }
                $action = trim($action);
                $signal = trim($signal);
                if ($action === '' || $signal === '') {
                    throw new RuntimeException(
                        'OWASYS_NAVIGATION_POST_ACTION_ENTRY_EMPTY'
                    );
                }
                if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $action) !== 1) {
                    throw new RuntimeException(
                        'OWASYS_NAVIGATION_POST_ACTION_INVALID:' . $action
                    );
                }
                if (isset($post[$signal])
                    && ($post[$signal]['route'] !== $route
                        || $post[$signal]['action'] !== $action)) {
                    throw new RuntimeException(
                        'OWASYS_NAVIGATION_POST_SIGNAL_AMBIGUOUS:' . $signal
                    );
                }
                $post[$signal] = [
                    'route' => $route,
                    'action' => $action,
                ];
            }
        }

        return ['get' => $get, 'post' => $post];
    }
}
