<?php
declare(strict_types=1);

namespace ASAP;

final class PackageRepository
{
    private string $sitesDir;
    /** @var array<string,Package> */
    private array $packages = [];

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
                throw new \RuntimeException('Package config must return array: ' . $file);
            }
            $package = new Package(dirname($file), $config);
            $this->packages[$package->slug] = $package;
        }

        if (!isset($this->packages['logandplay'])) {
            throw new \RuntimeException('Default package logandplay is required.');
        }
    }

    /** @return array<string,Package> */
    public function all(): array
    {
        return $this->packages;
    }

    public function get(string $slug): Package
    {
        if (!isset($this->packages[$slug])) {
            throw new \RuntimeException("Unknown package: {$slug}");
        }
        return $this->packages[$slug];
    }

    /** @return array{0:Package,1:list<string>,2:bool} */
    public function resolve(Request $request): array
    {
        $segments = $request->segments;
        $explicitPackage = false;

        if (isset($segments[0]) && isset($this->packages[$segments[0]])) {
            $package = $this->packages[$segments[0]];
            array_shift($segments);
            return [$package, $segments, true];
        }

        foreach ($this->packages as $package) {
            foreach ($package->domains as $domain) {
                if (strtolower($domain) === $request->host) {
                    return [$package, $segments, $explicitPackage];
                }
            }
        }

        return [$this->packages['logandplay'], $segments, $explicitPackage];
    }
}
