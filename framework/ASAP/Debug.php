<?php

declare(strict_types=1);

namespace ASAP;

/**
 * PUBLIC LEGACY-ALIGNED DEBUG UTILITY
 *
 * Role:
 *   Preserve the original ASAP Debug helper as a controlled diagnostic utility.
 *
 * Responsibility:
 *   Format diagnostic values without performing output side effects.
 *
 * Contract:
 *   Debug never echoes by itself. Caller decides representation/output.
 *
 * Since:
 *   P112D4C
 */
final class Debug
{
    /**
     * @param mixed $value Value to inspect.
     */
    public static function dump(mixed $value): string
    {
        return print_r($value, true);
    }
}
