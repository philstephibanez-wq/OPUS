<?php

declare(strict_types=1);

namespace Opus\Admin;

use Opus\Http\PublicResponse;
use Opus\Security\BlockedStateEvent;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Carry the native OPUS admin dashboard action control decision.
 *
 * Responsibility:
 *   Separate authorized administrator action effects from denied public opaque
 *   responses and protected admin diagnostics.
 *
 * Contract:
 *   Denied dashboard actions must expose only the public support response to the
 *   caller. Action diagnostics remain admin-only.
 */
final class AdminDashboardActionDecision
{
    /** @param array<string,mixed> $adminDiagnostics */
    private function __construct(
        private readonly bool $granted,
        private readonly string $eventId,
        private readonly string $action,
        private readonly array $adminDiagnostics,
        private readonly ?string $effect = null,
        private readonly ?PublicResponse $publicResponse = null,
        private readonly ?BlockedStateEvent $blockedStateEvent = null
    ) {
    }

    /** @param array<string,mixed> $adminDiagnostics */
    public static function granted(string $eventId, string $action, array $adminDiagnostics, string $effect): self
    {
        return new self(true, $eventId, $action, $adminDiagnostics, $effect);
    }

    /** @param array<string,mixed> $adminDiagnostics */
    public static function denied(string $eventId, string $action, array $adminDiagnostics, PublicResponse $publicResponse, BlockedStateEvent $blockedStateEvent): self
    {
        return new self(false, $eventId, $action, $adminDiagnostics, null, $publicResponse, $blockedStateEvent);
    }

    public function isGranted(): bool
    {
        return $this->granted;
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function action(): string
    {
        return $this->action;
    }

    /** @return array<string,mixed> */
    public function adminDiagnostics(): array
    {
        return $this->adminDiagnostics;
    }

    public function effect(): ?string
    {
        return $this->effect;
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
