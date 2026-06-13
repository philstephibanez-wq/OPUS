<?php

declare(strict_types=1);

namespace Opus\Recipe\Life;

use ASAP\Recipe\RecipeContext;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Execute robotized Opus life scenarios.
 *
 * Responsibility:
 *   Create isolated robot sessions, run each scenario step in order and return
 *   deterministic markers for the global recipe report.
 *
 * Contract:
 *   The runner never starts HTTP servers or browsers. It simulates life through
 *   official Opus objects and background CLIs only.
 */
final class LifeScenarioRunner
{
    /**
     * PUBLIC API
     *
     * @return string[] Markers emitted by the scenario.
     */
    public function run(RecipeContext $context, RobotScenario $scenario): array
    {
        $session = new RobotSession($scenario->actor());
        foreach ($scenario->steps() as $step) {
            $step->run($context, $session);
        }

        return ['OPUS_LIFE_' . strtoupper($scenario->scenarioName()) . '_OK'];
    }
}
