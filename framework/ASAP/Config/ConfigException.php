<?php

declare(strict_types=1);

namespace ASAP\Config;

use RuntimeException;

/**
 * PUBLIC EXCEPTION
 *
 * Role:
 *   Represent explicit configuration contract failures.
 *
 * Since:
 *   P112D4A
 */
final class ConfigException extends RuntimeException
{
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
