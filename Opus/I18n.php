<?php
declare(strict_types=1);

namespace Opus;

final class I18n
{
    /** @var array<string,array<string,string>> */
    private array $cache = [];

    /** @return array<string,string> */
    public function dictionary(Package $package, string $lang): array
    {
        if (!$package->hasLanguage($lang)) {
            throw new \RuntimeException("Language {$lang} is not declared for package {$package->slug}");
        }

        $key = $package->slug . ':' . $lang;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $file = $package->dir . '/local/' . $lang . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("I18N file missing: {$file}");
        }

        $dict = require $file;
        if (!is_array($dict)) {
            throw new \RuntimeException("I18N file must return array: {$file}");
        }

        $this->cache[$key] = array_map('strval', $dict);
        return $this->cache[$key];
    }

    public function t(Package $package, string $lang, string $key): string
    {
        $dict = $this->dictionary($package, $lang);
        return $dict[$key] ?? '[*' . $key . '*]';
    }
}
