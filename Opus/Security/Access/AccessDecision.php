<?php
declare(strict_types=1);

namespace Opus\Security\Access;

/**
 * Immutable ACL decision value object.
 */
final class AccessDecision implements AccessDecisionInterface
{
    private bool $granted;
    private string $reason;
    /** @var array<string,mixed> */
    private array $context;

    /** @param array<string,mixed> $context */
    public function __construct(bool $granted, string $reason, array $context = [])
    {
        $this->granted = $granted;
        $this->reason = $reason;
        $this->context = $context;
    }

    public static function granted(string $reason, array $context = []): self
    {
        return new self(true, $reason, $context);
    }

    public static function denied(string $reason, array $context = []): self
    {
        return new self(false, $reason, $context);
    }

    public function isGranted(): bool
    {
        return $this->granted;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function toArray(): array
    {
        return [
            'granted' => $this->granted,
            'reason' => $this->reason,
            'context' => $this->context,
        ];
    }
}
