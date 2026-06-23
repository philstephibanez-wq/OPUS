<?php
declare(strict_types=1);

namespace Opus\Application;

final class ApplicationDefinition
{
    public string $slug;
    public string $name;
    public string $dir;
    public string $defaultLang;
    /** @var list<string> */
    public array $languages;
    /** @var list<string> */
    public array $domains;
    /** @var array<string,mixed> */
    public array $meta;

    /** @param array<string,mixed> $config */
    public function __construct(string $dir, array $config)
    {
        foreach (['slug', 'name', 'default_lang', 'languages'] as $key) {
            if (!array_key_exists($key, $config)) {
                throw new \RuntimeException("Application definition config missing key {$key} in {$dir}");
            }
        }
        $this->dir = $dir;
        $this->slug = (string)$config['slug'];
        $this->name = (string)$config['name'];
        $this->defaultLang = (string)$config['default_lang'];
        $this->languages = array_values(array_map('strval', (array)$config['languages']));
        $this->domains = array_values(array_map('strval', (array)($config['domains'] ?? [])));
        $this->meta = $config;
    }

    public function hasLanguage(string $lang): bool
    {
        return in_array($lang, $this->languages, true);
    }

    /** @return array<string,mixed> */
    public function routes(): array
    {
        $file = $this->dir . '/routes.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Routes file missing for application {$this->slug}: {$file}");
        }
        $routes = require $file;
        if (!is_array($routes)) {
            throw new \RuntimeException("Routes file must return array for application {$this->slug}: {$file}");
        }
        return $routes;
    }

    /** @return array<string,mixed> */
    public function content(): array
    {
        $file = $this->dir . '/content.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Content file missing for application {$this->slug}: {$file}");
        }
        $content = require $file;
        if (!is_array($content)) {
            throw new \RuntimeException("Content file must return array for application {$this->slug}: {$file}");
        }
        return $content;
    }
}
