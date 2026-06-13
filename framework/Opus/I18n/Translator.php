<?php

declare(strict_types=1);

namespace Opus\I18n;

/*
 * OPUS_REFBOOK:
 *   domain: I18N
 *   role: Class Translator belongs to the I18N Opus framework domain.
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
 * PUBLIC SERVICE
 *
 * Role:
 *   Translate simple and pluralized messages for one locale.
 *
 * Responsibility:
 *   Apply parameters and plural category selection to catalog templates.
 *
 * Contract:
 *   No silent fallback. Missing keys, forms or parameters remain explicit.
 *
 * Since:
 *   P112D4A
 */
final class Translator
{
    public function __construct(
        private readonly TranslationCatalog $catalog,
        private readonly PluralRuleInterface $pluralRule
    ) {
    }

    /**
     * PUBLIC API
     *
     * @param string $key Message key.
     * @param array<string,string|int|float> $params Replacement parameters.
     *
     * @return string Translated message.
     */
    public function translate(string $key, array $params = []): string
    {
        return $this->interpolate($this->catalog->message($key), $params);
    }

    /**
     * PUBLIC API
     *
     * @param string $key Plural key.
     * @param int $count Count.
     * @param array<string,string|int|float> $params Replacement parameters.
     *
     * @return string Translated plural message.
     */
    public function plural(string $key, int $count, array $params = []): string
    {
        $params['count'] = $count;
        $category = $this->pluralRule->select($count);

        return $this->interpolate($this->catalog->plural($key, $category), $params);
    }

    /**
     * @param array<string,string|int|float> $params
     */
    private function interpolate(string $template, array $params): string
    {
        foreach ($params as $name => $value) {
            $template = str_replace('{' . $name . '}', (string) $value, $template);
        }

        return $template;
    }
}
