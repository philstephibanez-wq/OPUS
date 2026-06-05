<?php

declare(strict_types=1);

namespace ASAP\Controller;

use RuntimeException;

/**
 * PUBLIC EXCEPTION
 *
 * Role:
 *   Represent explicit controller/dispatcher contract failures.
 *
 * Contract:
 *   No controller fallback, no implicit action, no silent response coercion.
 *
 * Since:
 *   P112D4B
 */
final class ControllerException extends RuntimeException
{
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
