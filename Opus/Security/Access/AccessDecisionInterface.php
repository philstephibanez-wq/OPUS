<?php
declare(strict_types=1);

namespace Opus\Security\Access;

/**
 * Result of an OPUS access-control decision.
 */
interface AccessDecisionInterface
{
    public function isGranted(): bool;

    public function reason(): string;

    /** @return array<string,mixed> */
    public function toArray(): array;
}
