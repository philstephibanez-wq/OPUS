<?php

declare(strict_types=1);

namespace ASAP\TEMPLATE;

use RuntimeException;

/**
 * PUBLIC LEGACY-ALIGNED EXCEPTION
 *
 * Role:
 *   Represent explicit template adapter failures.
 *
 * Since:
 *   P112D4C
 */
final class TemplateException extends RuntimeException
{
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
