<?php

declare(strict_types=1);

namespace ASAP;

use RuntimeException;

/**
 * PUBLIC LEGACY-ALIGNED EXCEPTION
 *
 * Role:
 *   Preserve the original ASAP top-level exception concept in PHP 8 form.
 *
 * Responsibility:
 *   Carry explicit framework failure codes.
 *
 * Contract:
 *   No generic hidden failure. Every failure must expose a stable ASAP code.
 *
 * Since:
 *   P112D4C
 */
class Exception extends RuntimeException
{
    public static function because(string $code, string $detail = ''): static
    {
        return new static($detail === '' ? $code : $code . ': ' . $detail);
    }
}
