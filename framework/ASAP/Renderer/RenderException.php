<?php

declare(strict_types=1);

namespace ASAP\Renderer;

use RuntimeException;

/**
 * PUBLIC EXCEPTION
 *
 * Role:
 *   Represent explicit renderer contract failures.
 *
 * Contract:
 *   Renderer errors are visible. Missing templates or invalid data do not fallback.
 *
 * Since:
 *   P112D4B
 */
final class RenderException extends RuntimeException
{
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
