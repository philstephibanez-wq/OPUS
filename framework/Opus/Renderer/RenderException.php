<?php

declare(strict_types=1);

namespace Opus\Renderer;

use RuntimeException;

/*
 * OPUS_REFBOOK:
 *   domain: RENDERER
 *   role: Class RenderException belongs to the RENDERER Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the RENDERER domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - renderer-overview
 *   diagrams:
 *     - renderer-runtime
 * END_OPUS_REFBOOK
 */
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
