<?php
declare(strict_types=1);

use Opus\I18n\Locale;

final class OwasysLocaleRegistry
{
    /** @var array<string,string> */
    private const BASE_NAMES = [
        'bg'=>'Български','hr'=>'Hrvatski','cs'=>'Čeština','da'=>'Dansk','nl'=>'Nederlands',
        'en'=>'English','et'=>'Eesti','fi'=>'Suomi','fr'=>'Français','de'=>'Deutsch','el'=>'Ελληνικά',
        'hu'=>'Magyar','ga'=>'Gaeilge','it'=>'Italiano','lv'=>'Latviešu','lt'=>'Lietuvių','mt'=>'Malti',
        'pl'=>'Polski','pt'=>'Português','ro'=>'Română','sk'=>'Slovenčina','sl'=>'Slovenščina',
        'es'=>'Español','sv'=>'Svenska','uk'=>'Українська',
    ];

    /** @var array<string,string> */
    private const REGIONAL_NAMES = [
        'bg-BG'=>'Български',
        'hr-HR'=>'Hrvatski',
        'cs-CZ'=>'Čeština',
        'da-DK'=>'Dansk',
        'nl-NL'=>'Nederlands',
        'nl-BE'=>'Nederlands (België)',
        'en-IE'=>'English',
        'en-MT'=>'English (Malta)',
        'et-EE'=>'Eesti',
        'fi-FI'=>'Suomi',
        'fr-FR'=>'Français',
        'fr-BE'=>'Français (Belgique)',
        'fr-LU'=>'Français (Luxembourg)',
        'fr-CH'=>'Français (Suisse)',
        'de-DE'=>'Deutsch',
        'de-AT'=>'Deutsch (Österreich)',
        'de-BE'=>'Deutsch (Belgien)',
        'de-LU'=>'Deutsch (Luxemburg)',
        'de-CH'=>'Deutsch (Schweiz)',
        'el-GR'=>'Ελληνικά',
        'el-CY'=>'Ελληνικά (Κύπρος)',
        'hu-HU'=>'Magyar',
        'ga-IE'=>'Gaeilge',
        'it-IT'=>'Italiano',
        'it-CH'=>'Italiano (Svizzera)',
        'lv-LV'=>'Latviešu',
        'lt-LT'=>'Lietuvių',
        'mt-MT'=>'Malti',
        'pl-PL'=>'Polski',
        'pt-PT'=>'Português',
        'ro-RO'=>'Română',
        'sk-SK'=>'Slovenčina',
        'sl-SI'=>'Slovenščina',
        'es-ES'=>'Español',
        'sv-SE'=>'Svenska',
        'sv-FI'=>'Svenska (Finland)',
        'uk-UA'=>'Українська',
    ];

    /** @var array<string,string> */
    private const REGION_FLAGS = [
        'AT'=>'at','BE'=>'be','BG'=>'bg','CH'=>'ch','CY'=>'cy','CZ'=>'cz','DE'=>'de','DK'=>'dk',
        'EE'=>'ee','ES'=>'es','FI'=>'fi','FR'=>'fr','GR'=>'el','HR'=>'hr','HU'=>'hu','IE'=>'ie',
        'IT'=>'it','LT'=>'lt','LU'=>'lu','LV'=>'lv','MT'=>'mt','NL'=>'nl','PL'=>'pl','PT'=>'pt',
        'RO'=>'ro','SE'=>'se','SI'=>'si','SK'=>'sk','UA'=>'uk',
    ];

    /** @var list<string> */
    private array $configuredCodes;

    /** @var array<string,string> */
    private array $languageDefaults;

    /** @param array<string,mixed> $siteConfig */
    public function __construct(private readonly array $siteConfig)
    {
        $configured = [];

        foreach ((array) ($this->siteConfig['locales'] ?? []) as $code) {
            if (!is_string($code)) {
                continue;
            }

            $locale = new Locale($code);

            if ($locale->region === null) {
                throw new RuntimeException(
                    'OWASYS_SELECTOR_BASE_LOCALE_FORBIDDEN:'
                    . $locale->value
                );
            }

            if (!isset(self::BASE_NAMES[$locale->language])) {
                throw new RuntimeException(
                    'OWASYS_LOCALE_NAME_MISSING:'
                    . $locale->value
                );
            }

            if (!isset(self::REGIONAL_NAMES[$locale->value])) {
                throw new RuntimeException(
                    'OWASYS_REGIONAL_LOCALE_NAME_MISSING:'
                    . $locale->value
                );
            }

            $configured[$locale->value] = $locale->value;
        }

        if ($configured === []) {
            throw new RuntimeException('OWASYS_LOCALES_EMPTY');
        }

        $i18n = is_array($this->siteConfig['i18n'] ?? null)
            ? $this->siteConfig['i18n']
            : [];
        $defaults = [];

        foreach ((array) ($i18n['language_defaults'] ?? []) as $language => $code) {
            if (!is_string($language) || !is_string($code)) {
                throw new RuntimeException(
                    'OWASYS_LANGUAGE_DEFAULT_INVALID'
                );
            }

            $languageLocale = new Locale($language);
            $regionalLocale = new Locale($code);

            if (
                $languageLocale->region !== null
                || $regionalLocale->region === null
                || $regionalLocale->language !== $languageLocale->language
                || !isset($configured[$regionalLocale->value])
            ) {
                throw new RuntimeException(
                    'OWASYS_LANGUAGE_DEFAULT_INVALID:'
                    . $language
                    . ':'
                    . $code
                );
            }

            $defaults[$languageLocale->language] = $regionalLocale->value;
        }

        $configuredLanguages = [];

        foreach ($configured as $code) {
            $configuredLanguages[(new Locale($code))->language] = true;
        }

        foreach (array_keys($configuredLanguages) as $language) {
            if (!isset($defaults[$language])) {
                throw new RuntimeException(
                    'OWASYS_LANGUAGE_DEFAULT_MISSING:'
                    . $language
                );
            }
        }

        $defaultLocale = new Locale(
            (string) ($this->siteConfig['default_locale'] ?? '')
        );

        if (!isset($configured[$defaultLocale->value])) {
            throw new RuntimeException(
                'OWASYS_DEFAULT_LOCALE_UNSUPPORTED:'
                . $defaultLocale->value
            );
        }

        $this->configuredCodes = array_values($configured);
        $this->languageDefaults = $defaults;
    }

    /** @return list<string> */
    public function codes(): array
    {
        return $this->configuredCodes;
    }

    /** @return array<string,string> */
    public function languageDefaults(): array
    {
        return $this->languageDefaults;
    }

    public function resolveExplicit(string $candidate): ?string
    {
        $candidate = trim($candidate);

        if ($candidate === '') {
            return null;
        }

        try {
            $locale = new Locale($candidate);
        } catch (Throwable) {
            return null;
        }

        if (in_array($locale->value, $this->configuredCodes, true)) {
            return $locale->value;
        }

        if ($locale->script !== null || $locale->region !== null) {
            return null;
        }

        return $this->languageDefaults[$locale->language] ?? null;
    }

    public function name(string $code): string
    {
        $locale = new Locale($code);
        if ($locale->region !== null) {
            $name = self::REGIONAL_NAMES[$locale->value] ?? null;
            if (!is_string($name)) throw new RuntimeException('OWASYS_REGIONAL_LOCALE_UNKNOWN:' . $locale->value);
            return $name;
        }
        $name = self::BASE_NAMES[$locale->language] ?? null;
        if (!is_string($name)) throw new RuntimeException('OWASYS_LOCALE_UNKNOWN:' . $locale->value);
        return $name;
    }

    public function flagCode(string $code): string
    {
        $locale = new Locale($code);
        if ($locale->region === null) return $locale->language;
        $flag = self::REGION_FLAGS[$locale->region] ?? null;
        if (!is_string($flag)) throw new RuntimeException('OWASYS_REGIONAL_FLAG_UNKNOWN:' . $locale->value);
        return $flag;
    }
}
