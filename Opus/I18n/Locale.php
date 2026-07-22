<?php
declare(strict_types=1);

namespace Opus\I18n;

final readonly class Locale implements LocaleInterface
{
    public string $value;
    public string $language;

    public function __construct(string $locale)
    {
        $normalized = strtolower(str_replace('_', '-', trim($locale)));

        if (
            preg_match(
                '/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/',
                $normalized
            ) !== 1
        ) {
            throw TranslationException::because(
                'OPUS_I18N_LOCALE_INVALID',
                $locale
            );
        }

        $this->value = $normalized;
        $this->language = explode('-', $normalized, 2)[0];
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
