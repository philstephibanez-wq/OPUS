<?php

declare(strict_types=1);

namespace ASAP\Url;

use RuntimeException;

/**
 * PUBLIC EXCEPTION
 *
 * Role:
 *   Represent URL generation contract failures.
 *
 * Since:
 *   P112D4B
 */
final class UrlException extends RuntimeException
{
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
