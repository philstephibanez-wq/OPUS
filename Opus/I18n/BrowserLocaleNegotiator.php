<?php
declare(strict_types=1);

namespace Opus\I18n;

/** RFC-style Accept-Language negotiation constrained to application locales. */
final class BrowserLocaleNegotiator implements BrowserLocaleNegotiatorInterface
{
    /** @var list<Locale> */
    private array $supported;
    private Locale $default;

    /** @param list<string> $supportedLocales */
    private function __construct(array $supportedLocales, string $defaultLocale)
    {
        $map = [];
        foreach ($supportedLocales as $locale) {
            $normalized = new Locale($locale);
            $map[$normalized->value] = $normalized;
        }
        if ($map === []) throw TranslationException::because('OPUS_I18N_SUPPORTED_LOCALES_EMPTY');
        $this->supported = array_values($map);
        $default = new Locale($defaultLocale);
        $this->default = $map[$default->value] ?? throw TranslationException::because(
            'OPUS_I18N_DEFAULT_LOCALE_UNSUPPORTED',
            $default->value
        );
    }

    public static function forLocales(array $supportedLocales, string $defaultLocale): self
    {
        return new self($supportedLocales, $defaultLocale);
    }

    public function negotiate(?string $acceptLanguage): Locale
    {
        $header = trim((string) $acceptLanguage);
        if ($header === '') return $this->default;
        $weighted = [];
        foreach (explode(',', $header) as $order => $entry) {
            $parts = array_map('trim', explode(';', $entry));
            $tag = $parts[0] ?? '';
            $quality = 1.0;
            foreach (array_slice($parts, 1) as $parameter) {
                if (preg_match('/^q=([01](?:\.[0-9]{0,3})?)$/i', $parameter, $matches) === 1) {
                    $quality = (float) $matches[1];
                }
            }
            if ($tag !== '' && $quality > 0) {
                $weighted[] = ['tag' => $tag, 'q' => $quality, 'order' => $order];
            }
        }
        usort($weighted, static fn (array $a, array $b): int => $b['q'] <=> $a['q'] ?: $a['order'] <=> $b['order']);
        foreach ($weighted as $candidate) {
            if ($candidate['tag'] === '*') return $this->default;
            $matched = $this->match($candidate['tag']);
            if ($matched instanceof Locale) return $matched;
        }
        return $this->default;
    }

    public function match(?string $requestedLocale): ?Locale
    {
        $requested = trim((string) $requestedLocale);
        if ($requested === '') return null;
        try {
            $locale = new Locale($requested);
        } catch (\Throwable) {
            return null;
        }
        foreach ($this->supported as $supported) {
            if ($supported->value === $locale->value) return $supported;
        }
        for ($parent = $locale->parent(); $parent instanceof Locale; $parent = $parent->parent()) {
            foreach ($this->supported as $supported) {
                if ($supported->value === $parent->value) return $supported;
            }
        }
        if ($this->default->language === $locale->language) return $this->default;
        foreach ($this->supported as $supported) {
            if ($supported->language === $locale->language) return $supported;
        }
        return null;
    }
}
