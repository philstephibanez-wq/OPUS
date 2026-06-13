<?php

declare(strict_types=1);

namespace Opus\I18n;

use RuntimeException;

/*
 * OPUS_REFBOOK:
 *   domain: I18N
 *   role: Class TranslationException belongs to the I18N Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the I18N domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - i18n-overview
 *   diagrams:
 *     - i18n-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC EXCEPTION
 *
 * Role:
 *   Represent an explicit I18N contract failure.
 *
 * Responsibility:
 *   Carry stable failure codes for locale, catalog, key and plural errors.
 *
 * Contract:
 *   No silent fallback. Missing locale, message or plural form must fail clearly.
 *
 * Since:
 *   P112D4A
 */
final class TranslationException extends RuntimeException
{
    /**
     * PUBLIC FACTORY
     *
     * @param string $code Stable I18N error code.
     * @param string $detail Optional diagnostic detail.
     *
     * @return self Explicit I18N exception.
     */
    public static function because(string $code, string $detail = ''): self
    {
        return new self($detail === '' ? $code : $code . ': ' . $detail);
    }
}
