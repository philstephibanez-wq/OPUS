<?php

declare(strict_types=1);

namespace ASAP\Menu;

use RuntimeException;

/**
 * PUBLIC EXCEPTION
 *
 * Role:
 *   Represent menu contract failures.
 *
 * Since:
 *   P112D4B
 */
final class MenuException extends RuntimeException
{
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
