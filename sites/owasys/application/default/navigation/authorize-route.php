<?php
declare(strict_types=1);

use Opus\Fsm\FsmProcessor;

/**
 * Authorize direct access to one target state through the same ACL + FSM rules
 * used by rendered navigation.
 *
 * @return array<string,mixed>
 */
return static function (
    FsmProcessor $fsm,
    string $currentState,
    string $targetState,
    array $runtimeContext,
    string $profile,
    array $acl
): array {
    if ($targetState === '') {
        throw new RuntimeException('OWASYS_ROUTE_TARGET_STATE_REQUIRED');
    }
    if ($targetState === $currentState) {
        return [
            'contract' => 'OWASYS_ROUTE_AUTHORIZATION_V1',
            'authorized' => true,
            'event' => '',
            'from_state' => $currentState,
            'to_state' => $targetState,
        ];
    }

    $allowedEvents = is_array($acl[$profile] ?? null) ? $acl[$profile] : [];
    $allowAll = in_array('*', $allowedEvents, true);

    foreach ($fsm->transitions() as $transition) {
        if (!is_array($transition)) {
            continue;
        }
        $from = (string) ($transition['from'] ?? '');
        $event = (string) ($transition['event'] ?? '');
        $to = (string) ($transition['to'] ?? '');
        if ($to !== $targetState || ($from !== $currentState && $from !== '*') || $event === '') {
            continue;
        }
        if (!$allowAll && !in_array($event, $allowedEvents, true)) {
            continue;
        }

        try {
            $result = $fsm->transition($currentState, $event, $runtimeContext);
        } catch (Throwable) {
            continue;
        }

        return [
            'contract' => 'OWASYS_ROUTE_AUTHORIZATION_V1',
            'authorized' => true,
            'event' => $event,
            'from_state' => $currentState,
            'to_state' => (string) ($result['to_state'] ?? ''),
            'transition' => $result,
        ];
    }

    throw new RuntimeException('OWASYS_ROUTE_ACL_FSM_DENIED:' . $profile . ':' . $currentState . ':' . $targetState);
};
