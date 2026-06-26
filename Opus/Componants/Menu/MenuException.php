<?php

declare(strict_types=1);

namespace Opus\Menu;

use RuntimeException;

/*
 * OPUS_REFBOOK:
 *   domain: MENU
 *   role: Class MenuException belongs to the MENU Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the MENU domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - menu-overview
 *   diagrams:
 *     - menu-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC EXCEPTION
 *
 * Role:
 *   Represent menu contract failures.
 *
 * Since:
 *   P112D4B
 */
final class MenuException extends RuntimeException
 implements MenuExceptionInterface {
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
