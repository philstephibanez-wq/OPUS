<?php
declare(strict_types=1);

namespace Owasys\Application\I18n;

use RuntimeException;

final class Translator
{
    /** @var array<string,string> */
    private array $messages;
    private string $locale;

    /** @param list<string> $locales */
    public static function load(string $siteRoot, array $locales, string $defaultLocale, string $requestedLocale): self
    {
        $locale = in_array($requestedLocale, $locales, true) ? $requestedLocale : $defaultLocale;
        if (!in_array($locale, $locales, true)) {
            throw new RuntimeException('OWASYS_LOCALE_INVALID:' . $locale);
        }

        $load = static function (string $code) use ($siteRoot): array {
            $file = rtrim($siteRoot, '/\\') . '/application/default/local/' . $code . '.php';
            if (!is_file($file)) {
                return [];
            }
            $messages = require $file;
            return is_array($messages) ? $messages : [];
        };

        $messages = array_replace($load('en'), $load($defaultLocale), $load($locale));
        $instance = new self();
        $instance->locale = $locale;
        $instance->messages = array_filter($messages, 'is_string');
        return $instance;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function translate(string $key): string
    {
        $value = $this->messages[$key] ?? null;
        return is_string($value) && $value !== '' ? $value : '[[' . $key . ']]';
    }

    public function __invoke(string $key): string
    {
        return $this->translate($key);
    }
}
