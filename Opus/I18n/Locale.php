<?php
declare(strict_types=1);

namespace Opus\I18n;

/** Canonical BCP 47-style locale value accepting both hyphen and underscore input. */
final readonly class Locale implements LocaleInterface
{
    public string $value;
    public string $language;
    public ?string $script;
    public ?string $region;

    public function __construct(string $locale)
    {
        $input = trim(str_replace('_', '-', $locale));
        if (preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $input) !== 1) {
            throw TranslationException::because('OPUS_I18N_LOCALE_INVALID', $locale);
        }
        $parts = explode('-', $input);
        $parts[0] = strtolower($parts[0]);
        $script = null;
        $region = null;
        foreach ($parts as $index => $part) {
            if ($index === 0) continue;
            if ($script === null && preg_match('/^[A-Za-z]{4}$/', $part) === 1) {
                $parts[$index] = ucfirst(strtolower($part));
                $script = $parts[$index];
                continue;
            }
            if ($region === null && (preg_match('/^[A-Za-z]{2}$/', $part) === 1 || preg_match('/^[0-9]{3}$/', $part) === 1)) {
                $parts[$index] = strtoupper($part);
                $region = $parts[$index];
                continue;
            }
            $parts[$index] = strtolower($part);
        }
        $this->value = implode('-', $parts);
        $this->language = $parts[0];
        $this->script = $script;
        $this->region = $region;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function parent(): ?self
    {
        $parts = explode('-', $this->value);
        if (count($parts) === 1) return null;
        array_pop($parts);
        return new self(implode('-', $parts));
    }

    public function fallbackChain(): array
    {
        $chain = [];
        $cursor = $this;
        do {
            array_unshift($chain, $cursor);
            $cursor = $cursor->parent();
        } while ($cursor instanceof self);
        return $chain;
    }
}
