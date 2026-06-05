<?php

declare(strict_types=1);

namespace ASAP;

/**
 * PUBLIC LEGACY-ALIGNED VALIDATOR
 *
 * Role:
 *   Preserve the original ASAP validator domain with explicit PHP 8 methods.
 *
 * Responsibility:
 *   Provide deterministic scalar validations.
 *
 * Contract:
 *   Validation returns booleans only. It does not render errors or mutate data.
 *
 * Since:
 *   P112D4C
 */
final class Validator
{
    public static function notEmpty(string $value): bool
    {
        return trim($value) !== '';
    }

    public static function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function integer(string $value): bool
    {
        return preg_match('/^-?\d+$/', $value) === 1;
    }
}
