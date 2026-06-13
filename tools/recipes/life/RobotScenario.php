<?php

declare(strict_types=1);

namespace Opus\Recipe\Life;

/**
 * PUBLIC CONTRACT
 *
 * Role:
 *   Define one robotized life scenario.
 *
 * Responsibility:
 *   Declare the actor and ordered steps used to simulate a real Opus flow.
 *
 * Contract:
 *   Life scenarios are registered in the global manifest and therefore evolve
 *   with framework features.
 */
interface RobotScenario
{
    /** PUBLIC API: stable scenario name. */
    public function scenarioName(): string;

    /** PUBLIC API: actor that executes the scenario. */
    public function actor(): RobotActor;

    /** @return RobotStep[] */
    public function steps(): array;
}
