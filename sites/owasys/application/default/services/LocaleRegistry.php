<?php
declare(strict_types=1);

use Opus\I18n\Locale;

final class OwasysLocaleRegistry
{
    /** @var array<string,string> */
    private const NAMES = [
        'bg'=>'Български','hr'=>'Hrvatski','cs'=>'Čeština','da'=>'Dansk','nl'=>'Nederlands',
        'en'=>'English','et'=>'Eesti','fi'=>'Suomi','fr'=>'Français','de'=>'Deutsch','el'=>'Ελληνικά',
        'hu'=>'Magyar','ga'=>'Gaeilge','it'=>'Italiano','lv'=>'Latviešu','lt'=>'Lietuvių','mt'=>'Malti',
        'pl'=>'Polski','pt'=>'Português','ro'=>'Română','sk'=>'Slovenčina','sl'=>'Slovenščina',
        'es'=>'Español','sv'=>'Svenska','uk'=>'Українська',
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
            if (!isset(self::NAMES[$locale->language])) {
                throw new RuntimeException('OWASYS_LOCALE_NAME_MISSING:' . $locale->value);
            }
            $configured[$locale->value] = $locale->value;
        }
        if ($configured === []) throw new RuntimeException('OWASYS_LOCALES_EMPTY');
        return array_values($configured);
    }

    public function name(string $code): string
    {
        $locale = new Locale($code);
        $name = self::NAMES[$locale->language] ?? null;
        if (!is_string($name)) throw new RuntimeException('OWASYS_LOCALE_UNKNOWN:' . $locale->value);
        $qualifiers = array_values(array_filter([$locale->script, $locale->region], 'is_string'));
        return $qualifiers === [] ? $name : $name . ' (' . implode('-', $qualifiers) . ')';
    }

    public function flagCode(string $code): string
    {
        return (new Locale($code))->language;
    }
}
