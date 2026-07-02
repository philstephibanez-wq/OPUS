<?php
declare(strict_types=1);

/**
 * P7_OPS_LANGUAGE_SELECTOR_CORE
 *
 * Provides the visible OPS language selector and minimal i18n helpers.
 * Supported locales are deliberately explicit: fr and en.
 */

if (!function_exists('p7ops_h')) {
    function p7ops_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('p7ops_language')) {
    function p7ops_language(): string
    {
        $language = strtolower((string) ($_GET['lang'] ?? 'fr'));

        return in_array($language, ['fr', 'en'], true) ? $language : 'fr';
    }
}

if (!function_exists('p7ops_current_site')) {
    function p7ops_current_site(): string
    {
        $site = trim((string) ($_GET['site'] ?? 'site-alpha'));

        return $site !== '' ? $site : 'site-alpha';
    }
}

if (!function_exists('p7ops_i18n_catalog')) {
    function p7ops_i18n_catalog(): array
    {
        return [
            'fr' => [
                'language' => 'Langue',
                'active_language' => 'Langue active : Français',
                'dashboard' => 'Dashboard',
                'operations' => 'Operations',
                'command_center' => 'Command Center',
                'navigation' => 'Navigation',
                'diagnostics' => 'Diagnostics',
                'health_hub' => 'Health Hub',
            ],
            'en' => [
                'language' => 'Language',
                'active_language' => 'Active language: English',
                'dashboard' => 'Dashboard',
                'operations' => 'Operations',
                'command_center' => 'Command Center',
                'navigation' => 'Navigation',
                'diagnostics' => 'Diagnostics',
                'health_hub' => 'Health Hub',
            ],
        ];
    }
}

if (!function_exists('p7ops_t')) {
    function p7ops_t(string $key, ?string $language = null): string
    {
        $catalog = p7ops_i18n_catalog();
        $locale = $language ?? p7ops_language();

        return (string) ($catalog[$locale][$key] ?? $catalog['fr'][$key] ?? $key);
    }
}

if (!function_exists('p7ops_language_url')) {
    function p7ops_language_url(string $path, ?string $language = null, ?string $site = null): string
    {
        $route = str_starts_with($path, '/') ? $path : '/' . $path;
        $query = [
            'site' => $site ?? p7ops_current_site(),
            'lang' => $language ?? p7ops_language(),
        ];

        return $route . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('p7ops_language_selector')) {
    function p7ops_language_selector(?string $currentUri = null): string
    {
        $language = p7ops_language();
        $site = p7ops_current_site();
        $path = parse_url($currentUri ?? ($_SERVER['REQUEST_URI'] ?? '/opus-lstsar-manager'), PHP_URL_PATH);
        $route = is_string($path) && $path !== '' ? $path : '/opus-lstsar-manager';

        $frUrl = p7ops_language_url($route, 'fr', $site);
        $enUrl = p7ops_language_url($route, 'en', $site);
        $frActive = $language === 'fr' ? ' is-active' : '';
        $enActive = $language === 'en' ? ' is-active' : '';

        return ''
            . '<aside class="ops-language-selector" data-contract="P7_OPS_LANGUAGE_SELECTOR_CORE" data-lang-active="' . p7ops_h($language) . '" data-site="' . p7ops_h($site) . '">'
            . '<span class="ops-language-selector__label">' . p7ops_h(p7ops_t('language')) . '</span>'
            . '<a class="ops-language-selector__choice' . $frActive . '" href="' . p7ops_h($frUrl) . '" hreflang="fr" lang="fr">FR</a>'
            . '<a class="ops-language-selector__choice' . $enActive . '" href="' . p7ops_h($enUrl) . '" hreflang="en" lang="en">EN</a>'
            . '<span class="ops-language-selector__active">' . p7ops_h(p7ops_t('active_language')) . '</span>'
            . '</aside>'
            . '<script data-contract="P7_OPS_LANGUAGE_SELECTOR_CORE">(function(){var params=new URLSearchParams(window.location.search);var lang=params.get("lang")||"fr";var site=params.get("site")||"site-alpha";document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("a[href^=\"/opus-lstsar-manager\"]").forEach(function(anchor){var href=anchor.getAttribute("href")||"";var url=new URL(href,window.location.origin);if(!url.searchParams.has("lang")){url.searchParams.set("lang",lang);}if(!url.searchParams.has("site")){url.searchParams.set("site",site);}anchor.setAttribute("href",url.pathname+"?"+url.searchParams.toString());});});})();</script>';
    }
}