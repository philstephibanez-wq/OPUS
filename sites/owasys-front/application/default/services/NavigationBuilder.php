<?php
declare(strict_types=1);

use Opus\File\StructuredFileLoader;
use Opus\Fsm\FsmProcessor;
use Opus\Security\Csrf\CsrfTokenManager;

/** Builds the human menu as a strict I18n projection of the canonical EFSM. */
final class OwasysNavigationBuilder
{
    private const MENU_CSRF_SCOPE = 'owasys.fsm.menu';

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
     * A4BF contract:
     * - no menu entry exists without a canonical EFSM signal+transition;
     * - navigation signals project only top-level resource items;
     * - command signals project only resource-operation submenus;
     * - menu labels are I18n keys carried by EFSM signal/state metadata;
     * - EFSM guards, including ACL guards, decide actionability;
     * - the diagram consumes the same action views, never a parallel model.
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
        ?array $currentApp,
        callable $routeUrl
    ): array {
        $signalRegistry = $this->signalRegistry($fsmConfig);
        $bindings = $this->requestBindings();
        $signalRoutes = $bindings['get'];
        $signalPostActions = $bindings['post'];
        $processor = new FsmProcessor(
            $fsmConfig,
            (new OwasysFsmGuardHandlers($this->security))
                ->forConfig($fsmConfig, $identity)
        );
        $fsmContext = $this->fsmContext($identity, $currentApp);
        $menuCsrf = is_array($identity)
            ? (new CsrfTokenManager())->issue(self::MENU_CSRF_SCOPE)
            : '';

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
                && (!$requiresCurrentApp || is_array($currentApp));

            $statesById[$stateId] = $state;
            $items[] = [
                'id' => $stateId,
                'state_type' => $stateType,
                'module' => $module,
                'route' => $route,
                'label_key' => $labelKey,
                'label' => '',
                'configured_visible' =>
                    ($navigation['visible'] ?? false) === true,
                'visible' => false,
                'allowed' => $allowed,
                'available' => $available,
                'requires_current_app' => $requiresCurrentApp,
                'active' => $stateId === $currentState,
                'order' => (int) ($navigation['order'] ?? (1000 + $ordinal)),
                'signals' => [],
                'has_signals' => false,
                'operations' => [],
                'has_operations' => false,
                'navigation_action' => null,
                'navigation_actionable' => false,
                'navigation_url' => '',
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
                $type = (string) ($definition['type'] ?? '');
                $targetIndex = $itemsById[$to];
                $target = $items[$targetIndex];
                $targetAvailable = ($target['allowed'] ?? false) === true
                    && ($target['available'] ?? false) === true;
                $inspection = $processor->inspectTransition(
                    $currentState,
                    $signal,
                    array_replace($fsmContext, [
                        'menu_signal' => $signal,
                        'menu_resource' => (string) (
                            $definition['resource'] ?? ''
                        ),
                        'menu_operation' => (string) (
                            $definition['operation'] ?? ''
                        ),
                    ])
                );
                $fsmEnabled = $this->inspectionEnables(
                    $inspection,
                    $transitionId
                );

                if ($type === 'navigation') {
                    $mappedRoute = $signalRoutes[$signal] ?? null;
                    $pureSelfLoop = $this->isPureNavigationSelfLoop(
                        $transition,
                        $definition,
                        $currentState,
                        $to
                    );
                    $hasRoute = is_string($mappedRoute) && $mappedRoute !== '';
                    $actionable = $hasRoute
                        && $targetAvailable
                        && $fsmEnabled
                        && !$pureSelfLoop;
                    $url = $hasRoute ? $routeUrl((string) $mappedRoute) : '';
                    $view = $this->signalView(
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
                        '',
                        $hasRoute,
                        true,
                        $targetAvailable,
                        (int) ($definition['menu_order']
                            ?? ($target['order'] ?? PHP_INT_MAX)),
                        $inspection,
                        $pureSelfLoop ? 'current_state' : ''
                    );
                    $globalSignals[] = $view;

                    if (($definition['menu'] ?? false) === true) {
                        if (($target['configured_visible'] ?? false) !== true) {
                            throw new RuntimeException(
                                'OWASYS_NAVIGATION_EFSM_MENU_TARGET_HIDDEN:' . $to
                            );
                        }
                        if (is_array($items[$targetIndex]['navigation_action'])) {
                            throw new RuntimeException(
                                'OWASYS_NAVIGATION_RESOURCE_SIGNAL_AMBIGUOUS:' . $to
                            );
                        }
                        $items[$targetIndex]['visible'] =
                            $targetAvailable && $fsmEnabled;
                        $items[$targetIndex]['navigation_action'] = $view;
                        $items[$targetIndex]['navigation_actionable'] = $actionable;
                        $items[$targetIndex]['navigation_url'] = $url;
                    }
                    continue;
                }

                if ($type === 'command') {
                    $view = $this->globalMenuCommandView(
                        $transitionId,
                        $signal,
                        $definition,
                        $to,
                        $target,
                        $targetAvailable,
                        $fsmEnabled,
                        $inspection,
                        $routeUrl,
                        $statesById,
                        $menuCsrf
                    );
                    $globalSignals[] = $view;
                    if (($definition['menu'] ?? false) === true
                        && ($view['menu_actionable'] ?? false) === true) {
                        $menuState = trim((string) (
                            $definition['menu_state'] ?? $to
                        ));
                        if (!isset($itemsById[$menuState])) {
                            throw new RuntimeException(
                                'OWASYS_NAVIGATION_MENU_STATE_UNKNOWN:'
                                . $signal . ':' . $menuState
                            );
                        }
                        $items[$itemsById[$menuState]]['operations'][] = $view;
                    }
                    continue;
                }

                /* Outcome/system signals never become human menu controls. */
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

            $view = $this->signalView(
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
                '',
                is_string($mappedRoute) && $mappedRoute !== '',
                $from === $currentState,
                $targetAvailable,
                (int) ($definition['menu_order']
                    ?? ($target['order'] ?? PHP_INT_MAX)),
                $inspection,
                ''
            );
            $items[$sourceIndex]['signals'][] = $view;

            if ($from === $currentState
                && $isCommand
                && ($definition['menu'] ?? false) === true
                && ($definition['origin'] ?? '') === 'user'
                && ($view['menu_actionable'] ?? false) === true) {
                $items[$sourceIndex]['operations'][] = $view;
            }
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
            usort(
                $items[$index]['operations'],
                static fn (array $left, array $right): int =>
                    ($left['order'] <=> $right['order'])
                    ?: strcmp((string) $left['signal'], (string) $right['signal'])
            );
            $items[$index]['has_operations'] =
                $items[$index]['operations'] !== [];
        }

        $hostIndex = $itemsById[$currentState];
        $items[$hostIndex]['global_host'] = true;
        $items[$hostIndex]['global_signals'] = $globalSignals;
        $items[$hostIndex]['has_global_signals'] = $globalSignals !== [];

        return $items;
    }

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $target
     * @param array<string,mixed> $inspection
     * @param callable(string):string $routeUrl
     * @param array<string,array<string,mixed>> $statesById
     * @return array<string,mixed>
     */
    private function globalMenuCommandView(
        string $transitionId,
        string $signal,
        array $definition,
        string $targetId,
        array $target,
        bool $targetAvailable,
        bool $fsmEnabled,
        array $inspection,
        callable $routeUrl,
        array $statesById,
        string $csrfToken
    ): array {
        $targetState = $statesById[$targetId] ?? null;
        if (!is_array($targetState)) {
            throw new RuntimeException(
                'OWASYS_NAVIGATION_MENU_COMMAND_TARGET_UNKNOWN:' . $targetId
            );
        }
        $targetRoute = trim((string) ($targetState['route'] ?? ''));
        if ($targetRoute === '') {
            throw new RuntimeException(
                'OWASYS_NAVIGATION_MENU_COMMAND_ROUTE_MISSING:' . $targetId
            );
        }
        $url = $routeUrl($targetRoute);
        $actionable = $targetAvailable && $fsmEnabled && $csrfToken !== '';
        return $this->signalView(
            $transitionId,
            $signal,
            $definition,
            $targetId,
            $target,
            $url,
            false,
            $actionable,
            true,
            'owasys_fsm_signal',
            $signal,
            $csrfToken,
            true,
            true,
            $targetAvailable,
            (int) ($definition['menu_order'] ?? PHP_INT_MAX),
            $inspection,
            ''
        );
    }

    /**
     * @param array<string,mixed>|null $identity
     * @param array<string,mixed>|null $currentApp
     * @return array<string,mixed>
     */
    private function fsmContext(
        ?array $identity,
        ?array $currentApp
    ): array {
        return [
            'identity' => $identity,
            'is_authenticated' => is_array($identity),
            'roles' => is_array($identity['roles'] ?? null)
                ? array_values(array_filter($identity['roles'], 'is_string'))
                : [],
            'has_current_app' => is_array($currentApp),
            'current_app' => $currentApp,
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
        string $csrfToken,
        bool $hasRoute,
        bool $activeSource,
        bool $targetAvailable,
        int $order,
        ?array $inspection,
        string $projectionReason
    ): array {
        $reason = $projectionReason;
        $failedGuards = [];
        $guardResults = [];
        $actions = [];
        $fsmEnabled = false;

        if (is_array($inspection)) {
            $fsmEnabled = ($inspection['enabled'] ?? false) === true;
            $failedGuards = array_values(array_filter(
                is_array($inspection['failed_guards'] ?? null)
                    ? $inspection['failed_guards']
                    : [],
                'is_string'
            ));
            $guardResults = is_array($inspection['guard_results'] ?? null)
                ? $inspection['guard_results']
                : [];
            $actions = array_values(array_filter(
                is_array($inspection['actions'] ?? null)
                    ? $inspection['actions']
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
            'label_key' => (string) ($definition['label_key'] ?? ''),
            'label' => '',
            'signal_type' => (string) ($definition['type'] ?? ''),
            'signal_origin' => (string) ($definition['origin'] ?? ''),
            'resource' => (string) ($definition['resource'] ?? ''),
            'operation' => (string) ($definition['operation'] ?? ''),
            'menu_state' => (string) ($definition['menu_state'] ?? ''),
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
            'csrf_token' => $csrfToken,
            'has_route' => $hasRoute,
            'active_source' => $activeSource,
            'target_available' => $targetAvailable,
            'fsm_enabled' => $fsmEnabled,
            'fsm_reason' => $reason,
            'failed_guards' => $failedGuards,
            'guard_results' => $guardResults,
            'actions' => $actions,
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
     * @return array<string,array<string,mixed>>
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
            $labelKey = trim((string) ($definition['label_key'] ?? ''));
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
            if ($menu && $origin !== 'user') {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_SIGNAL_MENU_ORIGIN_INVALID:' . $id
                );
            }
            if ($menu && $labelKey === '') {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_SIGNAL_MENU_I18N_KEY_REQUIRED:' . $id
                );
            }
            if ($menu && $type === 'command') {
                $menuState = trim((string) ($definition['menu_state'] ?? ''));
                $resource = trim((string) ($definition['resource'] ?? ''));
                $operation = trim((string) ($definition['operation'] ?? ''));
                if ($menuState === '' || $resource === '' || $operation === '') {
                    throw new RuntimeException(
                        'OWASYS_NAVIGATION_COMMAND_MENU_METADATA_REQUIRED:' . $id
                    );
                }
            }
            $registry[$id] = [
                'type' => $type,
                'origin' => $origin,
                'menu' => $menu,
                'menu_order' => (int) ($definition['menu_order'] ?? PHP_INT_MAX),
                'label_key' => $labelKey,
                'menu_state' => (string) ($definition['menu_state'] ?? ''),
                'resource' => (string) ($definition['resource'] ?? ''),
                'operation' => (string) ($definition['operation'] ?? ''),
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
