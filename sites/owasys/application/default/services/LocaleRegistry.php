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
        'bg-BG'=>'Български (България)',
        'hr-HR'=>'Hrvatski (Hrvatska)',
        'cs-CZ'=>'Čeština (Česko)',
        'da-DK'=>'Dansk (Danmark)',
        'nl-NL'=>'Nederlands (Nederland)',
        'nl-BE'=>'Nederlands (België)',
        'en-IE'=>'English (Ireland)',
        'en-MT'=>'English (Malta)',
        'et-EE'=>'Eesti (Eesti)',
        'fi-FI'=>'Suomi (Suomi)',
        'fr-FR'=>'Français (France)',
        'fr-BE'=>'Français (Belgique)',
        'fr-LU'=>'Français (Luxembourg)',
        'fr-CH'=>'Français (Suisse)',
        'de-DE'=>'Deutsch (Deutschland)',
        'de-AT'=>'Deutsch (Österreich)',
        'de-BE'=>'Deutsch (Belgien)',
        'de-LU'=>'Deutsch (Luxemburg)',
        'de-CH'=>'Deutsch (Schweiz)',
        'el-GR'=>'Ελληνικά (Ελλάδα)',
        'el-CY'=>'Ελληνικά (Κύπρος)',
        'hu-HU'=>'Magyar (Magyarország)',
        'ga-IE'=>'Gaeilge (Éire)',
        'it-IT'=>'Italiano (Italia)',
        'it-CH'=>'Italiano (Svizzera)',
        'lv-LV'=>'Latviešu (Latvija)',
        'lt-LT'=>'Lietuvių (Lietuva)',
        'mt-MT'=>'Malti (Malta)',
        'pl-PL'=>'Polski (Polska)',
        'pt-PT'=>'Português (Portugal)',
        'ro-RO'=>'Română (România)',
        'sk-SK'=>'Slovenčina (Slovensko)',
        'sl-SI'=>'Slovenščina (Slovenija)',
        'es-ES'=>'Español (España)',
        'sv-SE'=>'Svenska (Sverige)',
        'sv-FI'=>'Svenska (Finland)',
        'uk-UA'=>'Українська (Україна)',
    ];

    /** @var array<string,string> */
    private const REGION_FLAGS = [
        'AT'=>'at','BE'=>'be','BG'=>'bg','CH'=>'ch','CY'=>'cy','CZ'=>'cz','DE'=>'de','DK'=>'dk',
        'EE'=>'ee','ES'=>'es','FI'=>'fi','FR'=>'fr','GR'=>'el','HR'=>'hr','HU'=>'hu','IE'=>'ie',
        'IT'=>'it','LT'=>'lt','LU'=>'lu','LV'=>'lv','MT'=>'mt','NL'=>'nl','PL'=>'pl','PT'=>'pt',
        'RO'=>'ro','SE'=>'se','SI'=>'si','SK'=>'sk','UA'=>'uk',
    ];

    /** @param array<string,mixed> $siteConfig */
    public function __construct(private readonly array $siteConfig)
    {
    }

    /** @return list<string> */
    public function codes(): array
    {
        $configured = [];
        foreach ((array) ($this->siteConfig['locales'] ?? []) as $code) {
            if (!is_string($code)) continue;
            $locale = new Locale($code);
            if (!isset(self::BASE_NAMES[$locale->language])) {
                throw new RuntimeException('OWASYS_LOCALE_NAME_MISSING:' . $locale->value);
            }
            if ($locale->region !== null && !isset(self::REGIONAL_NAMES[$locale->value])) {
                throw new RuntimeException('OWASYS_REGIONAL_LOCALE_NAME_MISSING:' . $locale->value);
            }
            $configured[$locale->value] = $locale->value;
        }
        if ($configured === []) throw new RuntimeException('OWASYS_LOCALES_EMPTY');
        return array_values($configured);
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
