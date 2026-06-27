<?php
declare(strict_types=1);

namespace Opus\Application;

use Opus\Http\Request;

/**
 * Registry of integrated OPUS application definitions.
 *
 * Contract:
 * - discovers available OPUS applications under /sites;
 * - never requires a hardcoded application name;
 * - resolves explicit application path segment first, then declared domains;
 * - reports an explicit resolution failure when no application can be selected.
 */
final class ApplicationRegistry implements ApplicationRegistryInterface
{
    private string $sitesDir;

    /** @var array<string,ApplicationDefinition> */
    private array $applications = [];

    public function __construct(string $rootDir)
    {
        $this->sitesDir = rtrim($rootDir, '/\\') . '/sites';
        $this->load();
    }

    private function load(): void
    {
        if (!is_dir($this->sitesDir)) {
            $this->applications = [];
            return;
        }

        foreach (glob($this->sitesDir . '/*/application.php') ?: [] as $file) {
            $config = require $file;
            if (!is_array($config)) {
                throw new \RuntimeException('Application definition config must return array: ' . $file);
            }
            $application = new ApplicationDefinition(dirname($file), $config);
            $this->applications[$application->slug] = $application;
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

        if (isset($segments[0]) && isset($this->applications[$segments[0]])) {
            $application = $this->applications[$segments[0]];
            array_shift($segments);
            return [$application, $segments, true];
        }

        foreach ($this->applications as $application) {
            foreach ($application->domains as $domain) {
                if (strtolower($domain) === $request->host) {
                    return [$application, $segments, false];
                }
            }
        }

        if (count($this->applications) === 1) {
            $application = reset($this->applications);
            if ($application instanceof ApplicationDefinition) {
                return [$application, $segments, false];
            }
        }

        throw new \RuntimeException('OPUS_APPLICATION_NOT_RESOLVED: no application matched host/path and no hardcoded default is allowed.');
    }
}