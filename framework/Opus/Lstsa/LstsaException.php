<?php

declare(strict_types=1);

namespace Opus\Lstsa;

use RuntimeException;

/*
 * OPUS_REFBOOK:
 *   domain: LSTSA
 *   role: Class LstsaException belongs to the LSTSA Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the LSTSA domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - lstsa-overview
 *   diagrams:
 *     - lstsa-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC Lstsa EXCEPTION
 *
 * Role:
 *   Carry explicit Load/Secure/Transform/Store/Archive contract failures.
 */
final class LstsaException extends RuntimeException
{
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
