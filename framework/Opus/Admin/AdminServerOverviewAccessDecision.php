<?php

declare(strict_types=1);

namespace Opus\Admin;

use Opus\Http\PublicResponse;
use Opus\Security\BlockedStateEvent;
use RuntimeException;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Carry the admin server-overview route authorization result.
 *
 * Responsibility:
 *   Separate allowed admin diagnostics from the public opaque denial response.
 *
 * Contract:
 *   Denied decisions must expose only PublicResponse to callers. Detailed reason
 *   strings remain admin/log/dashboard data.
 */
final class AdminServerOverviewAccessDecision
{
    /** @param array<string,mixed> $diagnostics */
    private function __construct(
        private readonly bool $allowed,
        private readonly string $eventId,
        private readonly array $diagnostics,
        private readonly ?PublicResponse $publicResponse = null,
        private readonly ?BlockedStateEvent $blockedStateEvent = null
    ) {
    }

    /** @param array<string,mixed> $diagnostics */
    public static function allowed(string $eventId, array $diagnostics): self
    {
        return new self(true, $eventId, $diagnostics);
    }

    /** @param array<string,mixed> $diagnostics */
    public static function denied(string $eventId, array $diagnostics, PublicResponse $publicResponse, BlockedStateEvent $blockedStateEvent): self
    {
        return new self(false, $eventId, $diagnostics, $publicResponse, $blockedStateEvent);
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
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    public function publicResponse(): PublicResponse
    {
        if ($this->publicResponse === null) {
            throw new RuntimeException('OPUS_ADMIN_SERVER_OVERVIEW_PUBLIC_RESPONSE_MISSING');
        }

        return $this->publicResponse;
    }

    public function blockedStateEvent(): ?BlockedStateEvent
    {
        return $this->blockedStateEvent;
    }
}