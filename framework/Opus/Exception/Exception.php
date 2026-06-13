<?php

declare(strict_types=1);

namespace Opus\Exception;

use RuntimeException;

/*
 * OPUS_REFBOOK:
 *   domain: EXCEPTION
 *   role: Class Exception belongs to the EXCEPTION Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the EXCEPTION domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - exception-overview
 *   diagrams:
 *     - exception-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY-ALIGNED EXCEPTION
 *
 * Role:
 *   Preserve the original Opus top-level exception concept in PHP 8 form.
 *
 * Responsibility:
 *   Carry explicit framework failure codes.
 *
 * Contract:
 *   No generic hidden failure. Every failure must expose a stable Opus code.
 *
 * Since:
 *   P112D4C
 */
class Exception extends RuntimeException
{
    public static function because(string $code, string $detail = ''): static
    {
        return new static($detail === '' ? $code : $code . ': ' . $detail);
    }
}
