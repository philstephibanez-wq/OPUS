<?php
declare(strict_types=1);

use Opus\File\StructuredFileLoader;

final class OwasysNavigationBuilder
{
    public function __construct(
        private readonly string $siteRoot,
        private readonly OwasysRuntimeSecurity $security
    ) {
    }

    /**
     * Builds the menu directly from the navigation FSM.
     *
     * Contract:
     * - one projected state = one menu entry;
     * - every projected outgoing signal = a submenu entry of its source state;
     * - state entries never perform transitions;
     * - only a signal belonging to the current state may be actionable;
     * - GET navigation URLs come from OPUS_SIGNAL_ROUTES_V2, never from a
     *   parallel menu registry.
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
            $requiresAuth =
                ($state['requires_auth'] ?? false) === true;
            $requiresCurrentApp =
                ($state['requires_current_app'] ?? false) === true;

            if ($module === '' || $route === '' || $labelKey === '') {
                throw new RuntimeException(
                    'OWASYS_NAVIGATION_STATE_INVALID:' . $stateId
                );
            }

            $allowed = !$requiresAuth
                || $this->security->isAllowed(
                    $identity,
                    $module,
                    'open'
                );
            $available = $allowed
                && (!$requiresCurrentApp || $hasCurrentApp);

            $item = [
                'id' => $stateId,
                'module' => $module,
                'label_key' => $labelKey,
                'label' => '',
                'url' => $routeUrl($route),
                'allowed' => $allowed,
                'available' => $available,
                'requires_current_app' => $requiresCurrentApp,
                'active' => $stateId === $currentState,
                'order' => (int) (
                    $navigation['order'] ?? (1000 + $stateOrdinal)
                ),
                'has_signals' => false,
                'signals' => [],
            ];
            $items[] = $item;
            $itemsById[$stateId] = count($items) - 1;
        }

        usort(
            $items,
            static fn (array $left, array $right): int =>
                ($left['order'] <=> $right['order'])
                ?: strcmp((string) $left['id'], (string) $right['id'])
        );

        $itemsById = [];
        foreach ($items as $index => $item) {
            $itemsById[(string) $item['id']] = $index;
        }

        $signalRoutes = $this->signalRoutes();
        $transitionIds = [];

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
                    'OWASYS_NAVIGATION_TRANSITION_DUPLICATE:'
                    . $transitionId
                );
            }
            $transitionIds[$transitionId] = true;

            // NMI is explicitly outside the finite state menu.
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

            if (!isset($itemsById[$from]) || !isset($itemsById[$to])) {
                continue;
            }

            $sourceIndex = $itemsById[$from];
            $targetIndex = $itemsById[$to];
            $target = $items[$targetIndex];
            $mappedRoute = $signalRoutes[$signal] ?? null;
            $hasRoute = is_string($mappedRoute);

            if ($hasRoute) {
                $targetState = $stateById[$to] ?? null;
                $targetRoute = is_array($targetState)
                    ? trim((string) ($targetState['route'] ?? ''))
                    : '';
                if ($targetRoute === '' || $mappedRoute !== $targetRoute) {
                    throw new RuntimeException(
                        'OWASYS_NAVIGATION_SIGNAL_ROUTE_TARGET_DIVERGENCE:'
                        . $signal . ':' . $mappedRoute . ':' . $to
                    );
                }
            }

            $actionable = $from === $currentState
                && ($target['allowed'] ?? false) === true
                && ($target['available'] ?? false) === true
                && $hasRoute;

            $items[$sourceIndex]['signals'][] = [
                'transition_id' => $transitionId,
                'signal' => $signal,
                'target' => $to,
                'target_label_key' => (string) ($target['label_key'] ?? ''),
                'target_label' => '',
                'url' => $actionable
                    ? $routeUrl((string) $mappedRoute)
                    : '',
                'actionable' => $actionable,
                'has_route' => $hasRoute,
                'active_source' => $from === $currentState,
                'target_available' =>
                    ($target['allowed'] ?? false) === true
                    && ($target['available'] ?? false) === true,
                'order' => (int) ($target['order'] ?? PHP_INT_MAX),
            ];
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
        }

        return $items;
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
                if (isset($result[$signal])
                    && $result[$signal] !== $route) {
                    throw new RuntimeException(
                        'OWASYS_NAVIGATION_SIGNAL_ROUTE_AMBIGUOUS:'
                        . $signal
                    );
                }
                $result[$signal] = $route;
            }
        }

        return $result;
    }
}