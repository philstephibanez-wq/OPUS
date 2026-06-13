<?php

declare(strict_types=1);

namespace Opus\I18n;

/*
 * OPUS_REFBOOK:
 *   domain: I18N
 *   role: Class LocaleCode belongs to the I18N Opus framework domain.
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
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Represent one normalized locale code.
 *
 * Responsibility:
 *   Validate and expose BCP47-like locale identifiers used by Opus I18N.
 *
 * Contract:
 *   Locale normalization only. No translation lookup and no fallback decision.
 *
 * Since:
 *   P112D4A
 */
final class LocaleCode
{
    public readonly string $value;

    public function __construct(string $locale)
    {
        $normalized = str_replace('_', '-', strtolower(trim($locale)));

        if (preg_match('/^[a-z]{2}(-[a-z0-9]{2,8})*$/', $normalized) !== 1) {
            throw TranslationException::because('OPUS_I18N_LOCALE_INVALID', $locale);
        }

        $this->value = $normalized;
    }

    /**
     * PUBLIC API
     *
     * @return string Normalized locale code.
     */
    public function toString(): string
    {
        return $this->value;
    }
}
