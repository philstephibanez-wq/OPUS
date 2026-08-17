<?php
declare(strict_types=1);

use Opus\File\StructuredFileLoader;

final class OwasysNavigationBuilder
{
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
     * Builds both the internal FSM navigation projection and the visible
     * OWASYS development bar from the canonical FSM.
     *
     * Contract:
     * - all canonical states stay in the returned projection so the fixed
     *   FSM diagram can validate labels and transition actionability;
     * - only states with navigation.visible=true belong to the visible bar;
     * - the visible bar contains direct state links, never duplicated signal
     *   dropdowns;
     * - a direct state URL is never taken directly from state.route: it is
     *   resolved from the exact menu=true navigation transition whose source
     *   is the runtime current state and whose target is that state;
     * - command/outcome/system signals remain FSM/diagram/profiler facts and
     *   never become visible global navigation entries;
     * - current-state transition actionability and ACL/availability remain
     *   authoritative;
     * - URLs come only from OPUS_SIGNAL_ROUTES_V2.
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
        $items = [];
        $itemsById = [];
        $stateById = [];
        $stateOrdinal = 0;

        foreach ((array) ($fsmConfig['states'] ?? []) as $state) {
            ++$stateOrdinal;
            if (!is_array($state)) {
                continue;
            }

            $stateId = trim((string) ($state['id'] ?? ''));
            if ($stateId === '' || $stateId === '*') {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_STATE_INVALID:' . $stateId
                );
            }
            if (isset($stateById[$stateId])) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_STATE_DUPLICATE:' . $stateId
                );
            }
            $stateById[$stateId] = $state;

            $navigation = is_array($state['navigation'] ?? null)
                ? $state['navigation']
                : [];
            $module = trim((string) ($state['module'] ?? $stateId));
            $route = trim((string) ($state['route'] ?? ''));
            $labelKey = trim((string) (
                $navigation['label']
                ?? $state['title_key']
                ?? ('menu.' . $module)
            ));
            $requiresAuth = ($state['requires_auth'] ?? false) === true;
            $requiresCurrentApp =
                ($state['requires_current_app'] ?? false) === true;

            if ($module === '' || $route === '' || $labelKey === '') {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_STATE_INVALID:' . $stateId
                );
            }

            $allowed = !$requiresAuth
                || $this->security->isAllowed($identity, $module, 'open');
            $available = $allowed
                && (!$requiresCurrentApp || $hasCurrentApp);

            $items[] = [
                'id' => $stateId,
                'module' => $module,
                'label_key' => $labelKey,
                'label' => '',
                'visible' => ($navigation['visible'] ?? false) === true,
                'allowed' => $allowed,
                'available' => $available,
                'requires_current_app' => $requiresCurrentApp,
                'active' => $stateId === $currentState,
                'order' => (int) (
                    $navigation['order'] ?? (1000 + $stateOrdinal)
                ),
                'url' => '',
                'entry_actionable' => false,
                'entry_transition_id' => '',
                'entry_signal' => '',
                'has_signals' => false,
                'signals' => [],
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

        $signalRegistry = $this->signalRegistry($fsmConfig);
        $signalRoutes = $this->signalRoutes();
        $transitionIds = [];
        $directTargets = [];

        foreach ((array) ($fsmConfig['transitions'] ?? []) as $transition) {
            if (!is_array($transition)) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_TRANSITION_INVALID'
                );
            }

            $transitionId = trim((string) ($transition['id'] ?? ''));
            $from = trim((string) ($transition['from'] ?? ''));
            $to = trim((string) (
                $transition['next_state']
                ?? $transition['nextState']
                ?? ''
            ));
            $signal = trim((string) ($transition['signal'] ?? ''));
            $interrupt = trim((string) ($transition['interrupt'] ?? ''));

            if ($transitionId === ''
                || $from === ''
                || $to === ''
                || $signal === '') {
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

            if ($interrupt === 'nmi') {
                continue;
            }
            if ($interrupt !== '') {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_INTERRUPT_INVALID:' . $interrupt
                );
            }
            if ($from === '*') {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_GLOBAL_SOURCE_FORBIDDEN:' . $signal
                );
            }
            if (!isset($signalRegistry[$signal])) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_SIGNAL_UNDECLARED:' . $signal
                );
            }
            if (($signalRegistry[$signal]['menu'] ?? false) !== true) {
                continue;
            }
            if (($signalRegistry[$signal]['type'] ?? '') !== 'navigation') {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_MENU_SIGNAL_TYPE_INVALID:' . $signal
                );
            }
            if (!isset($itemsById[$from]) || !isset($itemsById[$to])) {
                continue;
            }

            $mappedRoute = $signalRoutes[$signal] ?? null;
            if (!is_string($mappedRoute) || $mappedRoute === '') {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_MENU_SIGNAL_ROUTE_MISSING:' . $signal
                );
            }

            $sourceIndex = $itemsById[$from];
            $targetIndex = $itemsById[$to];
            $target = $items[$targetIndex];
            $actionable = $from === $currentState
                && ($target['allowed'] ?? false) === true
                && ($target['available'] ?? false) === true;
            $url = $actionable ? $routeUrl($mappedRoute) : '';

            $items[$sourceIndex]['signals'][] = [
                'transition_id' => $transitionId,
                'signal' => $signal,
                'signal_type' => 'navigation',
                'target' => $to,
                'target_label_key' => (string) ($target['label_key'] ?? ''),
                'target_label' => '',
                'url' => $url,
                'actionable' => $actionable,
                'has_route' => true,
                'trigger_route' => $mappedRoute,
                'active_source' => $from === $currentState,
                'target_available' =>
                    ($target['allowed'] ?? false) === true
                    && ($target['available'] ?? false) === true,
                'order' => (int) ($target['order'] ?? PHP_INT_MAX),
            ];

            if (!$actionable) {
                continue;
            }

            if (isset($directTargets[$to])) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_DIRECT_TARGET_AMBIGUOUS:'
                    . $currentState . ':' . $to
                );
            }
            $directTargets[$to] = $transitionId;
            $items[$targetIndex]['url'] = $url;
            $items[$targetIndex]['entry_actionable'] = true;
            $items[$targetIndex]['entry_transition_id'] = $transitionId;
            $items[$targetIndex]['entry_signal'] = $signal;
        }

        foreach ($items as $index => $item) {
            usort(
                $items[$index]['signals'],
                static fn (array $left, array $right): int =>
                    ($left['order'] <=> $right['order'])
                    ?: strcmp(
                        (string) $left['signal'],
                        (string) $right['signal']
                    )
            );
            $items[$index]['has_signals'] =
                $items[$index]['signals'] !== [];

            if (($items[$index]['visible'] ?? false) !== true
                || ($items[$index]['allowed'] ?? false) !== true
                || ($items[$index]['available'] ?? false) !== true) {
                continue;
            }
            if (($items[$index]['entry_actionable'] ?? false) !== true) {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_VISIBLE_TARGET_UNREACHABLE:'
                    . $currentState
                    . ':'
                    . (string) ($items[$index]['id'] ?? '')
                );
            }
        }

        return $items;
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
            $registry[$id] = ['type' => $type, 'menu' => $menu];
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
