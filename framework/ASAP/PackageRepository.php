<?php

declare(strict_types=1);

namespace ASAP;

/**
 * PUBLIC LEGACY COMPATIBILITY SHIM
 *
 * Role:
 *   Store explicitly registered legacy Package definitions.
 *
 * Contract:
 *   No filesystem auto-discovery. Unknown packages fail explicitly.
 *
 * Since:
 *   P112O
 */
final class PackageRepository
{
    /** @var array<string,Package> */
    private array $packages = [];

    /** @param Package[] $packages */
    public function __construct(array $packages = [])
    {
        foreach ($packages as $package) {
            $this->packages[$package->id()] = $package;
        }
    }

    /** @return Package[] */
    public function all(): array
    {
        return array_values($this->packages);
    }

    public function get(string $id): Package
    {
        if (!isset($this->packages[$id])) {
            throw Exception::because('ASAP_PACKAGE_NOT_FOUND', $id);
        }

        return $this->packages[$id];
    }

    public function resolve(string $id): Package
    {
        return $this->get($id);
    }
}
