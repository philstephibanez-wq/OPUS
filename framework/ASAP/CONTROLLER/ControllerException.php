<?php

declare(strict_types=1);

namespace ASAP\CONTROLLER;

use RuntimeException;

/**
 * PUBLIC LEGACY-ALIGNED EXCEPTION
 *
 * Role:
 *   Represent explicit controller/dispatcher contract failures.
 *
 * Contract:
 *   No controller fallback, no implicit action, no silent response coercion.
 *
 * Since:
 *   P112D4C
 */
final class ControllerException extends RuntimeException
{
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
