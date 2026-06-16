<?php

declare(strict_types=1);

namespace Opus\Security;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Carry the internal result of the public route control plane.
 *
 * Responsibility:
 *   Separate public execution allowance from protected administrator diagnostics
 *   and attach an optional blocked-state event for denied decisions.
 *
 * Contract:
 *   Diagnostics returned by this object are for protected admin, log and report
 *   usage only. Public renderers must not expose them.
 */
final class PublicControlDecision
{
    /** @param array<string,mixed> $adminDiagnostics */
    private function __construct(
        private readonly bool $allowed,
        private readonly string $eventId,
        private readonly array $adminDiagnostics,
        private readonly ?BlockedStateEvent $blockedStateEvent = null
    ) {
    }

    /** @param array<string,mixed> $adminDiagnostics */
    public static function allowed(string $eventId, array $adminDiagnostics): self
    {
        return new self(true, $eventId, $adminDiagnostics);
    }

    /** @param array<string,mixed> $adminDiagnostics */
    public static function denied(string $eventId, array $adminDiagnostics, BlockedStateEvent $blockedStateEvent): self
    {
        return new self(false, $eventId, $adminDiagnostics, $blockedStateEvent);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    /** @return array<string,mixed> */
    public function adminDiagnostics(): array
    {
        return $this->adminDiagnostics;
    }

    public function blockedStateEvent(): ?BlockedStateEvent
    {
        return $this->blockedStateEvent;
    }
}
