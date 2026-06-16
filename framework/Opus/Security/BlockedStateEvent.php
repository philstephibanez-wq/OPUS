<?php

declare(strict_types=1);

namespace Opus\Security;

use InvalidArgumentException;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Represent a blocked-state event produced by the OPUS FSM bastion.
 *
 * Responsibility:
 *   Keep public error output opaque while preserving protected administrator
 *   diagnostics for dashboard, logs and future notifications.
 *
 * Contract:
 *   This object is not a public response. Its diagnostics are admin-only. Public
 *   users may receive only the neutral support message derived from it.
 */
final class BlockedStateEvent
{
    private const PUBLIC_TITLE = 'Site temporairement bloqué.';
    private const PUBLIC_SUPPORT = 'Contactez le support.';

    private function __construct(
        private readonly string $eventId,
        private readonly string $site,
        private readonly string $routeKey,
        private readonly string $blockedState,
        private readonly string $reason,
        private readonly string $adminAction,
        private readonly string $severity
    ) {
        foreach ([
            'eventId' => $this->eventId,
            'site' => $this->site,
            'routeKey' => $this->routeKey,
            'blockedState' => $this->blockedState,
            'reason' => $this->reason,
            'adminAction' => $this->adminAction,
            'severity' => $this->severity,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('OPUS_BLOCKED_STATE_EVENT_FIELD_EMPTY: ' . $field);
            }
        }
    }

    public static function publicRequestBlocked(
        string $eventId,
        string $site,
        string $routeKey,
        string $blockedState,
        string $reason,
        string $adminAction,
        string $severity = 'warning'
    ): self {
        return new self($eventId, $site, $routeKey, $blockedState, $reason, $adminAction, $severity);
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function publicBody(): string
    {
        return self::PUBLIC_TITLE . "\n" . self::PUBLIC_SUPPORT;
    }

    /** @return array<string,string> */
    public function adminDiagnostics(): array
    {
        return [
            'event_id' => $this->eventId,
            'site' => $this->site,
            'route_key' => $this->routeKey,
            'blocked_state' => $this->blockedState,
            'reason' => $this->reason,
            'admin_action' => $this->adminAction,
            'severity' => $this->severity,
        ];
    }
}
