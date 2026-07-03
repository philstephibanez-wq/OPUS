<?php
declare(strict_types=1);

namespace Opus\Manager\Service;

/** OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE */
final class OpusManagerI18n
{
    /**
     * @return array<string, string>
     */
    public static function languages(): array
    {
        return [
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
    }

    public static function resolveLang(?string $lang): string
    {
        $candidate = strtolower(trim((string) $lang));
        return array_key_exists($candidate, self::languages()) ? $candidate : 'fr';
    }

    public static function languageName(?string $lang): string
    {
        $resolved = self::resolveLang($lang);
        return self::languages()[$resolved];
    }

    public static function optionsHtml(?string $selected): string
    {
        $selected = self::resolveLang($selected);
        $html = '';

        foreach (self::languages() as $code => $label) {
            $isSelected = $code === $selected ? ' selected' : '';
            $html .= '<option value="' . self::h($code) . '"' . $isSelected . '>' . self::h($label . ' — ' . strtoupper($code)) . '</option>';
        }

        return $html;
    }

    private static function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}