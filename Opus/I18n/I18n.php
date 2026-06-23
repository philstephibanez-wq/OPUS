<?php
declare(strict_types=1);

namespace Opus\I18n;

use Opus\Application\ApplicationDefinition;
final class I18n
{
    /** @var array<string,array<string,string>> */
    private array $cache = [];

    /** @return array<string,string> */
    public function dictionary(ApplicationDefinition $application, string $lang): array
    {
        if (!$application->hasLanguage($lang)) {
            throw new \RuntimeException("Language {$lang} is not declared for application {$application->slug}");
        }

        $key = $application->slug . ':' . $lang;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $file = $application->dir . '/local/' . $lang . '.php';
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

    public function t(ApplicationDefinition $application, string $lang, string $key): string
    {
        $dict = $this->dictionary($application, $lang);
        return $dict[$key] ?? '[*' . $key . '*]';
    }
}
