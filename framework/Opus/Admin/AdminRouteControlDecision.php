<?php

declare(strict_types=1);

namespace Opus\Admin;

use Opus\Http\PublicResponse;
use Opus\Security\BlockedStateEvent;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Carry the native OPUS admin dashboard route control decision.
 *
 * Responsibility:
 *   Separate authorized administrator dashboard payloads from denied public
 *   responses and protected blocked-state diagnostics.
 *
 * Contract:
 *   Denied admin route access must return only an opaque public response to the
 *   caller. Administrator diagnostics remain available only through protected
 *   admin/log/report surfaces.
 */
final class AdminRouteControlDecision
{
    /** @param array<string,mixed> $adminDiagnostics */
    private function __construct(
        private readonly bool $allowed,
        private readonly string $eventId,
        private readonly array $adminDiagnostics,
        private readonly ?AdminBlockedStateViewModel $adminViewModel = null,
        private readonly ?PublicResponse $publicResponse = null,
        private readonly ?BlockedStateEvent $blockedStateEvent = null
    ) {
    }

    /** @param array<string,mixed> $adminDiagnostics */
    public static function allowed(string $eventId, array $adminDiagnostics, AdminBlockedStateViewModel $adminViewModel): self
    {
        return new self(true, $eventId, $adminDiagnostics, $adminViewModel);
    }

    /** @param array<string,mixed> $adminDiagnostics */
    public static function denied(string $eventId, array $adminDiagnostics, PublicResponse $publicResponse, BlockedStateEvent $blockedStateEvent): self
    {
        return new self(false, $eventId, $adminDiagnostics, null, $publicResponse, $blockedStateEvent);
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

    public function adminViewModel(): ?AdminBlockedStateViewModel
    {
        return $this->adminViewModel;
    }

    public function publicResponse(): ?PublicResponse
    {
        return $this->publicResponse;
    }

    public function blockedStateEvent(): ?BlockedStateEvent
    {
        return $this->blockedStateEvent;
    }
}
