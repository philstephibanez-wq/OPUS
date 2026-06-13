<?php

declare(strict_types=1);

namespace Opus\Template;

use RuntimeException;

/*
 * OPUS_REFBOOK:
 *   domain: TEMPLATE
 *   role: Class TemplateException belongs to the TEMPLATE Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the TEMPLATE domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - template-overview
 *   diagrams:
 *     - template-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY-ALIGNED EXCEPTION
 *
 * Role:
 *   Represent explicit template adapter failures.
 *
 * Since:
 *   P112D4C
 */
final class TemplateException extends RuntimeException
{
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
