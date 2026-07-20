<?php
declare(strict_types=1);

namespace Opus\Application;

/**
 * Immutable definition of an integrated OPUS application.
 *
 * Routes are localized URL projections to FSM signals.
 * They must never select a page, controller or state directly.
 */
final class ApplicationDefinition implements ApplicationDefinitionInterface
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
                throw new \RuntimeException(
                    "Application definition config missing key {$key} in {$dir}"
                );
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

    public function initialState(): string
    {
        $state = trim((string)($this->meta['initial_state'] ?? ''));
        if ($state === '') {
            throw new \RuntimeException(
                "Application initial_state missing: {$this->slug}"
            );
        }

        return $state;
    }

    /**
     * Localized URL projection to FSM signals.
     *
     * Expected form:
     * [
     *   'fr' => ['structure' => 'open_structure'],
     *   'en' => ['structure' => 'open_structure'],
     * ]
     *
     * @return array<string,mixed>
     */
    public function routes(): array
    {
        $file = $this->dir . '/routes.php';
        if (!is_file($file)) {
            throw new \RuntimeException(
                "Routes file missing for application {$this->slug}: {$file}"
            );
        }

        $routes = require $file;
        if (!is_array($routes)) {
            throw new \RuntimeException(
                "Routes file must return array for application {$this->slug}: {$file}"
            );
        }

        foreach ($routes as $lang => $localizedRoutes) {
            if (!is_string($lang) || !is_array($localizedRoutes)) {
                throw new \RuntimeException(
                    "Routes file contains an invalid language map for {$this->slug}"
                );
            }

            foreach ($localizedRoutes as $url => $signal) {
                if (!is_string($url) || !is_string($signal) || trim($signal) === '') {
                    throw new \RuntimeException(
                        "Routes file must map localized URLs to non-empty FSM signals for {$this->slug}"
                    );
                }
            }
        }

        return $routes;
    }

    /** @return array<string,mixed> */
    public function content(): array
    {
        $file = $this->dir . '/content.php';
        if (!is_file($file)) {
            throw new \RuntimeException(
                "Content file missing for application {$this->slug}: {$file}"
            );
        }

        $content = require $file;
        if (!is_array($content)) {
            throw new \RuntimeException(
                "Content file must return array for application {$this->slug}: {$file}"
            );
        }

        return $content;
    }
}
