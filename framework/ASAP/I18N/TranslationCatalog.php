<?php

declare(strict_types=1);

namespace ASAP\I18N;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Carry one locale translation catalog.
 *
 * Responsibility:
 *   Expose simple messages and plural message forms for a validated locale.
 *
 * Contract:
 *   Data lookup only. No plural rule selection and no rendering side effect.
 *
 * Since:
 *   P112D4A
 */
final class TranslationCatalog
{
    /**
     * @param array<string,string> $messages Simple messages.
     * @param array<string,array<string,string>> $plurals Plural messages by key and category.
     */
    public function __construct(
        public readonly LocaleCode $locale,
        private readonly array $messages,
        private readonly array $plurals
    ) {
    }

    public function message(string $key): string
    {
        if (!array_key_exists($key, $this->messages)) {
            throw TranslationException::because('ASAP_I18N_MESSAGE_MISSING', $this->locale->toString() . '::' . $key);
        }

        return $this->messages[$key];
    }

    public function plural(string $key, string $category): string
    {
        if (!array_key_exists($key, $this->plurals)) {
            throw TranslationException::because('ASAP_I18N_PLURAL_KEY_MISSING', $this->locale->toString() . '::' . $key);
        }

        $forms = $this->plurals[$key];

        if (array_key_exists($category, $forms)) {
            return $forms[$category];
        }

        if (array_key_exists('other', $forms)) {
            return $forms['other'];
        }

        throw TranslationException::because('ASAP_I18N_PLURAL_FORM_MISSING', $this->locale->toString() . '::' . $key . '::' . $category);
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return $this->messages;
    }

    /** @return array<string,array<string,string>> */
    public function plurals(): array
    {
        return $this->plurals;
    }

    /** @return array{locale:string,messages:array<string,string>,plurals:array<string,array<string,string>>} */
    public function toArray(): array
    {
        return [
            'locale' => $this->locale->toString(),
            'messages' => $this->messages,
            'plurals' => $this->plurals,
        ];
    }
}
