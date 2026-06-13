<?php

declare(strict_types=1);

namespace Opus\View;

use RuntimeException;

/*
 * OPUS_REFBOOK:
 *   domain: VIEW
 *   role: Class ViewException belongs to the VIEW Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the VIEW domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - view-overview
 *   diagrams:
 *     - view-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY-ALIGNED EXCEPTION
 *
 * Role:
 *   Represent explicit View contract failures.
 *
 * Since:
 *   P112D4C
 */
final class ViewException extends RuntimeException
{
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
