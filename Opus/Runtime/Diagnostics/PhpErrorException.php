<?php
declare(strict_types=1);

namespace Opus\Runtime\Diagnostics;

final class PhpErrorException extends \ErrorException implements PhpErrorExceptionInterface
{
    public static function fromPhpError(int $severity, string $message, string $file, int $line): self
    {
        return new self($message, 0, $severity, $file, $line);
    }
}