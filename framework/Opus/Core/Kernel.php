<?php

declare(strict_types=1);

namespace Opus\Core;

/*
 * OPUS_REFBOOK:
 *   domain: CORE
 *   role: Class Kernel belongs to the CORE Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the CORE domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - core-overview
 *   diagrams:
 *     - core-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY COMPATIBILITY SHIM
 *
 * Role:
 *   Restore the top-level Kernel facade required by legacy package/front code.
 *
 * Contract:
 *   URL builders and package lookup only. handle() requires an explicit callable.
 *
 * Since:
 *   P112O
 */
final class Kernel
{
    public function __construct(
        private readonly string $rootDir,
        private readonly \ASAP\Package\PackageRepository $packages = new \ASAP\Package\PackageRepository()
    ) {
        if (trim($this->rootDir) === '') {
            throw \ASAP\Exception\Exception::because('OPUS_KERNEL_ROOT_EMPTY');
        }
    }

    public function rootDir(): string
    {
        return rtrim(str_replace('\\', '/', $this->rootDir), '/');
    }

    public function getPackage(string $id): \ASAP\Package\Package
    {
        return $this->packages->get($id);
    }

    public function pageUrl(string $path): string
    {
        return $this->buildLocalUrl('/' . ltrim($path, '/'));
    }

    public function apiUrl(string $path): string
    {
        return $this->buildLocalUrl('/api/' . ltrim($path, '/'));
    }

    public function assetUrl(string $path): string
    {
        return $this->buildLocalUrl('/assets/' . ltrim($path, '/'));
    }

    public function packageUrl(string $packageId, string $path): string
    {
        $this->getPackage($packageId);

        return $this->buildLocalUrl('/packages/' . rawurlencode($packageId) . '/' . ltrim($path, '/'));
    }

    public function handle(callable $handler): mixed
    {
        return $handler($this);
    }

    private function buildLocalUrl(string $path): string
    {
        if ($path === '' || $path[0] !== '/') {
            throw \ASAP\Exception\Exception::because('OPUS_KERNEL_URL_PATH_INVALID', $path);
        }

        return $path;
    }
}
