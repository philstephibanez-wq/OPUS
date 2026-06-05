<?php

declare(strict_types=1);

namespace ASAP\VIEW;

use RuntimeException;

/**
 * PUBLIC LEGACY-ALIGNED EXCEPTION
 *
 * Role:
 *   Represent explicit View contract failures.
 *
 * Since:
 *   P112D4C
 */
final class ViewException extends RuntimeException
{
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
