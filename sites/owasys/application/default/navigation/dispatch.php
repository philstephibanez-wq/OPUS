<?php
declare(strict_types=1);

use Opus\Fsm\FsmProcessor;

/**
 * Dispatch one backend navigation action through ACL + FSM.
 *
 * @return array<string,mixed>
 */
return static function (
    FsmProcessor $fsm,
    string $currentState,
    string $event,
    array $runtimeContext,
    string $profile,
    array $acl
): array {
    if ($event === '') {
        throw new RuntimeException('OWASYS_NAVIGATION_EVENT_REQUIRED');
    }

    $allowedEvents = is_array($acl[$profile] ?? null) ? $acl[$profile] : [];
    if (!in_array('*', $allowedEvents, true) && !in_array($event, $allowedEvents, true)) {
        throw new RuntimeException('OWASYS_NAVIGATION_ACL_DENIED:' . $profile . ':' . $event);
    }

    return $fsm->transition($currentState, $event, $runtimeContext);
};
