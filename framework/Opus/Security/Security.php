<?php

declare(strict_types=1);

namespace Opus\Security;

/*
 * OPUS_REFBOOK:
 *   domain: SECURITY
 *   role: Class Security belongs to the SECURITY Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the SECURITY domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - security-overview
 *   diagrams:
 *     - security-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY-COLLISION RECONCILIATION
 *
 * Role:
 *   Preserve the Opus SECURITY result concept inside the canonical Windows-safe
 *   `ASAP\Security` namespace/directory.
 *
 * Responsibility:
 *   Carry an explicit authorization result.
 *
 * Contract:
 *   Result object only. Guard decisions remain owned by FsmGuard/AclGuard.
 *
 * Since:
 *   P112D4E
 *
 * Deepened:
 *   P112D4F
 */
final class Security
{
    public function __construct(
        public readonly bool $allowed,
        public readonly string $reason = ''
    ) {
    }

    public static function allow(string $reason = ''): self
    {
        return new self(true, $reason);
    }

    public static function deny(string $reason): self
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('OPUS_SECURITY_DENY_REASON_EMPTY');
        }

        return new self(false, $reason);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function isDenied(): bool
    {
        return !$this->allowed;
    }

    public function assertAllowed(): void
    {
        if (!$this->allowed) {
            throw new \RuntimeException('OPUS_SECURITY_DENIED: ' . $this->reason);
        }
    }
}
