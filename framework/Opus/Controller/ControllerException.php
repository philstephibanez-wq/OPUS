<?php

declare(strict_types=1);

namespace Opus\Controller;

use RuntimeException;

/*
 * OPUS_REFBOOK:
 *   domain: CONTROLLER
 *   role: Class ControllerException belongs to the CONTROLLER Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the CONTROLLER domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - controller-overview
 *   diagrams:
 *     - controller-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY-ALIGNED EXCEPTION
 *
 * Role:
 *   Represent explicit controller/dispatcher contract failures.
 *
 * Contract:
 *   No controller fallback, no implicit action, no silent response coercion.
 *
 * Since:
 *   P112D4C
 */
final class ControllerException extends RuntimeException
{
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
