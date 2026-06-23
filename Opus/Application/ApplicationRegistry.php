<?php
declare(strict_types=1);

namespace Opus\Application;

use Opus\Http\Request;
final class ApplicationRegistry
{
    private string $sitesDir;
    /** @var array<string,ApplicationDefinition> */
    private array $applications = [];

    public function __construct(string $rootDir)
    {
        $this->sitesDir = $rootDir . '/sites';
        $this->load();
    }

    private function load(): void
    {
        if (!is_dir($this->sitesDir)) {
            throw new \RuntimeException('Sites directory missing: ' . $this->sitesDir);
        }

        foreach (glob($this->sitesDir . '/*/package.php') ?: [] as $file) {
            $config = require $file;
            if (!is_array($config)) {
                throw new \RuntimeException('Application definition config must return array: ' . $file);
            }
            $application = new ApplicationDefinition(dirname($file), $config);
            $this->applications[$application->slug] = $application;
        }

        if (!isset($this->applications['logandplay'])) {
            throw new \RuntimeException('Default application logandplay is required.');
        }
    }

    /** @return array<string,ApplicationDefinition> */
    public function all(): array
    {
        return $this->applications;
    }

    public function get(string $slug): ApplicationDefinition
    {
        if (!isset($this->applications[$slug])) {
            throw new \RuntimeException("Unknown application: {$slug}");
        }
        return $this->applications[$slug];
    }

    /** @return array{0:ApplicationDefinition,1:list<string>,2:bool} */
    public function resolve(Request $request): array
    {
        $segments = $request->segments;
        $explicitApplication = false;

        if (isset($segments[0]) && isset($this->applications[$segments[0]])) {
            $application = $this->applications[$segments[0]];
            array_shift($segments);
            return [$application, $segments, true];
        }

        foreach ($this->applications as $application) {
            foreach ($application->domains as $domain) {
                if (strtolower($domain) === $request->host) {
                    return [$application, $segments, $explicitApplication];
                }
            }
        }

        return [$this->applications['logandplay'], $segments, $explicitApplication];
    }
}
