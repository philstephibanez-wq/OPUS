<?php
declare(strict_types=1);

namespace Opus\I18n;

use RuntimeException;

final class TranslationException extends RuntimeException
{
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
