<?php

declare(strict_types=1);

namespace Opus\Form;

use RuntimeException;

/*
 * OPUS_REFBOOK:
 *   domain: FORM
 *   role: Class FormException belongs to the FORM Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the FORM domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - form-overview
 *   diagrams:
 *     - form-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC EXCEPTION
 *
 * Role:
 *   Represent explicit form contract failures.
 *
 * Since:
 *   P112D4B
 */
final class FormException extends RuntimeException
{
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
