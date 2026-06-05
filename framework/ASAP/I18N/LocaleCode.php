<?php

declare(strict_types=1);

namespace ASAP\I18N;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Represent one normalized locale code.
 *
 * Responsibility:
 *   Validate and expose BCP47-like locale identifiers used by ASAP I18N.
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
            throw TranslationException::because('ASAP_I18N_LOCALE_INVALID', $locale);
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
