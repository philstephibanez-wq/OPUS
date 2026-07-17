<?php
declare(strict_types=1);

use Opus\Fsm\FsmProcessor;

/**
 * Build the visible OWASYS navigation from executable FSM events.
 *
 * The FSM processor resolves state-specific transitions before wildcard
 * transitions. This projection therefore evaluates each event exactly once,
 * instead of rendering one item per matching transition declaration.
 *
 * @return list<array{event:string,label_key:string,order:int,target_state:string,target_route:string}>
 */
return static function (
    FsmProcessor $fsm,
    string $currentState,
    array $runtimeContext,
    string $profile,
    array $presentation,
    array $acl
): array {
    $allowedEvents = is_array($acl[$profile] ?? null) ? $acl[$profile] : [];
    $allowAll = in_array('*', $allowedEvents, true);
    $candidateEvents = [];

    foreach ($fsm->transitions() as $transition) {
        if (!is_array($transition) || ($transition['visual'] ?? false) !== true) {
            continue;
        }

        $from = (string) ($transition['from'] ?? '');
        $event = (string) ($transition['event'] ?? '');
        if ($event === '' || ($from !== $currentState && $from !== '*')) {
            continue;
        }
        if (!$allowAll && !in_array($event, $allowedEvents, true)) {
            continue;
        }
        if (!is_array($presentation[$event] ?? null)) {
            continue;
        }

        $candidateEvents[$event] = true;
    }

    $items = [];
    foreach (array_keys($candidateEvents) as $event) {
        try {
            $result = $fsm->transition($currentState, $event, $runtimeContext);
        } catch (Throwable) {
            continue;
        }

        $target = is_array($result['target_state'] ?? null) ? $result['target_state'] : [];
        $route = (string) ($target['route'] ?? '');
        if ($route === '') {
            continue;
        }

        $meta = $presentation[$event];
        $items[] = [
            'event' => $event,
            'label_key' => (string) ($meta['label_key'] ?? ''),
            'order' => (int) ($meta['order'] ?? 0),
            'target_state' => (string) ($result['to_state'] ?? ''),
            'target_route' => $route,
        ];
    }

    usort($items, static fn (array $left, array $right): int => $left['order'] <=> $right['order']);
    return $items;
};
