<?php

declare(strict_types=1);

namespace Opus\Package;

/*
 * OPUS_REFBOOK:
 *   domain: PACKAGE
 *   role: Class PackageRepository belongs to the PACKAGE Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the PACKAGE domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - package-overview
 *   diagrams:
 *     - package-runtime
 * END_OPUS_REFBOOK
 */
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
            throw \ASAP\Exception\Exception::because('OPUS_PACKAGE_NOT_FOUND', $id);
        }

        return $this->packages[$id];
    }

    public function resolve(string $id): Package
    {
        return $this->get($id);
    }
}
