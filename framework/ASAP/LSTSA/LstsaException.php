<?php

declare(strict_types=1);

namespace ASAP\LSTSA;

use RuntimeException;

/**
 * PUBLIC LSTSA EXCEPTION
 *
 * Role:
 *   Carry explicit Load/Secure/Transform/Store/Archive contract failures.
 */
final class LstsaException extends RuntimeException
{
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
