<?php

declare(strict_types=1);

namespace Opus\Recipe\Life;

use ASAP\Recipe\RecipeContext;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Represent one executable step inside a robotized life scenario.
 *
 * Responsibility:
 *   Bind a stable step name to an explicit callable operating on recipe context
 *   and robot session state.
 *
 * Contract:
 *   A step must throw on failure. Returning normally means the simulated user
 *   action is valid.
 */
final class RobotStep
{
    /** @param callable(RecipeContext,RobotSession):void $callback */
    public function __construct(public readonly string $name, private readonly mixed $callback)
    {
    }

    /** PUBLIC API: execute the step. */
    public function run(RecipeContext $context, RobotSession $session): void
    {
        ($this->callback)($context, $session);
    }
}
