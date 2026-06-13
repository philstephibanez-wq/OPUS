<?php

declare(strict_types=1);

namespace Opus\Recipe;

use RuntimeException;

/**
 * PUBLIC EXCEPTION
 *
 * Role:
 *   Represent one failed Opus recipe assertion.
 *
 * Responsibility:
 *   Carry a stable recipe failure code and a human-readable diagnostic.
 *
 * Contract:
 *   Recipe failures are explicit. A failed assertion must never be swallowed or
 *   converted to a silent warning.
 */
final class RecipeAssertionFailedException extends RuntimeException
{
    /**
     * PUBLIC FACTORY
     *
     * @param string $code Stable recipe failure code.
     * @param string $detail Optional diagnostic detail.
     *
     * @return self Failure exception.
     */
    public static function because(string $code, string $detail = ''): self
    {
        $message = trim($detail) === '' ? $code : $code . ': ' . $detail;

        return new self($message);
    }
}
