<?php
declare(strict_types=1);

final class OwasysLocaleRegistry
{
    /** @var array<string,string> */
    private const NAMES = [
        'bg' => 'Български',
        'hr' => 'Hrvatski',
        'cs' => 'Čeština',
        'da' => 'Dansk',
        'nl' => 'Nederlands',
        'en' => 'English',
        'et' => 'Eesti',
        'fi' => 'Suomi',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'el' => 'Ελληνικά',
        'hu' => 'Magyar',
        'ga' => 'Gaeilge',
        'it' => 'Italiano',
        'lv' => 'Latviešu',
        'lt' => 'Lietuvių',
        'mt' => 'Malti',
        'pl' => 'Polski',
        'pt' => 'Português',
        'ro' => 'Română',
        'sk' => 'Slovenčina',
        'sl' => 'Slovenščina',
        'es' => 'Español',
        'sv' => 'Svenska',
        'uk' => 'Українська',
    ];

    /** @param array<string,mixed> $siteConfig */
    public function __construct(private readonly array $siteConfig)
    {
    }

    /** @return list<string> */
    public function codes(): array
    {
        $configured = array_values(array_filter(
            (array) ($this->siteConfig['locales'] ?? []),
            'is_string'
        ));

        foreach ($configured as $code) {
            if (!isset(self::NAMES[$code])) {
                throw new RuntimeException('OWASYS_LOCALE_NAME_MISSING:' . $code);
            }
        }

        return $configured;
    }

    public function name(string $code): string
    {
        if (!isset(self::NAMES[$code])) {
            throw new RuntimeException('OWASYS_LOCALE_UNKNOWN:' . $code);
        }

        return self::NAMES[$code];
    }
}
