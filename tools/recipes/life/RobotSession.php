<?php

declare(strict_types=1);

namespace Opus\Recipe\Life;

/**
 * PUBLIC STATE OBJECT
 *
 * Role:
 *   Carry isolated state for one robot actor during a life scenario.
 *
 * Responsibility:
 *   Store route, locale, ACL, job and assertion data created by robot steps.
 *
 * Contract:
 *   Session state is per scenario and per actor. No PHP global session is used.
 */
final class RobotSession
{
    /** @var array<string,mixed> */
    private array $values = [];

    public function __construct(public readonly RobotActor $actor)
    {
    }

    /** PUBLIC API: write one scenario value. */
    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    /** PUBLIC API: read one scenario value or null. */
    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }
}
