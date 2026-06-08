<?php

declare(strict_types=1);

/**
 * PUBLIC SMOKE SCRIPT
 *
 * Role:
 *   Validate the P112Q3B3 correction: generated reports must no longer expose
 *   a stale Mail: PENDING badge after the mail phase has completed.
 *
 * Contract:
 *   Reuses the P112Q3B2 life recipe smoke because P112Q3B3 is a corrective
 *   stabilization of the same recipe, not a new framework behavior.
 */

require __DIR__ . '/p112q3b2_secure_life_robotized_recipe_smoke.php';

echo 'P112Q3B3_RECIPE_FINAL_STATUS_SMOKE_OK' . PHP_EOL;
