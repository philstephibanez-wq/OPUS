<?php

declare(strict_types=1);

namespace ASAP\Form;

use RuntimeException;

/**
 * PUBLIC EXCEPTION
 *
 * Role:
 *   Represent explicit form contract failures.
 *
 * Since:
 *   P112D4B
 */
final class FormException extends RuntimeException
{
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
