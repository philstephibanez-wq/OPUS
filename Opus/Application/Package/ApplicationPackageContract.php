<?php
declare(strict_types=1);

namespace Opus\Application\Package;

/**
 * Validates the Composer package contract for official OPUS applications.
 */
final class ApplicationPackageContract
{
    public const COMPOSER_TYPE = 'opus-application';
    public const DEFAULT_MANIFEST = 'opus.application.json';

    public function validatePackageDirectory(string $packageDir): ApplicationPackageManifest
    {
        $packageDir = rtrim($packageDir, DIRECTORY_SEPARATOR);
        if ($packageDir === '' || !is_dir($packageDir)) {
            throw new \RuntimeException('OPUS_APP_PACKAGE_DIR_MISSING: ' . $packageDir);
        }

        $composer = $this->readComposerJson($packageDir . DIRECTORY_SEPARATOR . 'composer.json');
        $this->assertComposerPackage($composer, $packageDir);

        $manifestPath = $packageDir . DIRECTORY_SEPARATOR . $this->manifestRelativePath($composer);
        $manifest = ApplicationPackageManifest::fromFile($manifestPath);

        if ($manifest->packageName() !== (string) $composer['name']) {
            throw new \RuntimeException('OPUS_APP_PACKAGE_MANIFEST_PACKAGE_MISMATCH: ' . $composer['name']);
        }

        return $manifest;
    }

    /** @param array<string,mixed> $composer */
    public function manifestRelativePath(array $composer): string
    {
        $extra = isset($composer['extra']) && is_array($composer['extra']) ? $composer['extra'] : [];
        $opus = isset($extra['opus']) && is_array($extra['opus']) ? $extra['opus'] : [];
        $manifest = trim((string) ($opus['application_manifest'] ?? self::DEFAULT_MANIFEST));
        if ($manifest === '' || str_contains($manifest, '..') || str_starts_with($manifest, '/') || str_starts_with($manifest, '\\')) {
            throw new \RuntimeException('OPUS_APP_PACKAGE_MANIFEST_PATH_INVALID: ' . $manifest);
        }

        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $manifest);
    }

    /** @return array<string,mixed> */
    private function readComposerJson(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException('OPUS_APP_PACKAGE_COMPOSER_JSON_MISSING: ' . $path);
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('OPUS_APP_PACKAGE_COMPOSER_JSON_INVALID: ' . $path);
        }

        return $decoded;
    }

    /** @param array<string,mixed> $composer */
    private function assertComposerPackage(array $composer, string $packageDir): void
    {
        $name = (string) ($composer['name'] ?? '');
        if (!preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/', $name)) {
            throw new \RuntimeException('OPUS_APP_PACKAGE_COMPOSER_NAME_INVALID: ' . $packageDir);
        }
        if ((string) ($composer['type'] ?? '') !== self::COMPOSER_TYPE) {
            throw new \RuntimeException('OPUS_APP_PACKAGE_COMPOSER_TYPE_INVALID: ' . $name);
        }
        if (!isset($composer['autoload']) || !is_array($composer['autoload'])) {
            throw new \RuntimeException('OPUS_APP_PACKAGE_AUTOLOAD_MISSING: ' . $name);
        }
        if (!isset($composer['autoload']['psr-4']) || !is_array($composer['autoload']['psr-4'])) {
            throw new \RuntimeException('OPUS_APP_PACKAGE_PSR4_MISSING: ' . $name);
        }
    }
}
