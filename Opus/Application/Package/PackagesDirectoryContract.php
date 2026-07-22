<?php
declare(strict_types=1);

namespace Opus\Application\Package;

/**
 * Validates the monorepo source directory for official OPUS application packages.
 *
 * The directory is a source convention only. Runtime installation remains
 * Composer-driven through path repositories or normal Composer repositories.
 */
final class PackagesDirectoryContract implements PackagesDirectoryContractInterface
{
    public const DEFAULT_PACKAGES_DIR = 'packages';

    private ApplicationPackageContract $packageContract;

    public function __construct(?ApplicationPackageContract $packageContract = null)
    {
        $this->packageContract = $packageContract ?? new ApplicationPackageContract();
    }

    /**
     * @return list<ApplicationPackageManifest>
     */
    public function validateProjectPackages(string $projectRoot, string $packagesDirName = self::DEFAULT_PACKAGES_DIR): array
    {
        $packagesDir = $this->packagesDirectoryPath($projectRoot, $packagesDirName);
        $directories = $this->packageDirectories($packagesDir);
        if ($directories === []) {
            throw new \RuntimeException('OPUS_PACKAGES_DIRECTORY_EMPTY: ' . $packagesDir);
        }

        $manifests = [];
        foreach ($directories as $directory) {
            $manifests[] = $this->packageContract->validatePackageDirectory($directory);
        }

        usort($manifests, static fn (ApplicationPackageManifest $a, ApplicationPackageManifest $b): int => $a->packageName() <=> $b->packageName());

        return $manifests;
    }

    /**
     * @param list<string> $requiredPackageNames
     * @return list<ApplicationPackageManifest>
     */
    public function validateRequiredPackages(string $projectRoot, array $requiredPackageNames, string $packagesDirName = self::DEFAULT_PACKAGES_DIR): array
    {
        $manifests = $this->validateProjectPackages($projectRoot, $packagesDirName);
        $found = [];
        foreach ($manifests as $manifest) {
            $found[$manifest->packageName()] = true;
        }

        foreach ($requiredPackageNames as $requiredPackageName) {
            if (!isset($found[$requiredPackageName])) {
                throw new \RuntimeException('OPUS_PACKAGES_DIRECTORY_REQUIRED_PACKAGE_MISSING: ' . $requiredPackageName);
            }
        }

        return $manifests;
    }

    public function assertRootComposerHasPackagesPathRepository(string $projectRoot): void
    {
        $composerPath = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'composer.json';
        if (!is_file($composerPath)) {
            throw new \RuntimeException('OPUS_ROOT_COMPOSER_JSON_MISSING: ' . $composerPath);
        }

        $data = json_decode((string) file_get_contents($composerPath), true);
        if (!is_array($data)) {
            throw new \RuntimeException('OPUS_ROOT_COMPOSER_JSON_INVALID: ' . $composerPath);
        }

        $repositories = isset($data['repositories']) && is_array($data['repositories']) ? $data['repositories'] : [];
        foreach ($repositories as $repository) {
            if (!is_array($repository)) {
                continue;
            }
            if (($repository['type'] ?? '') === 'path' && str_replace('\\', '/', (string) ($repository['url'] ?? '')) === 'packages/*') {
                return;
            }
        }

        throw new \RuntimeException('OPUS_ROOT_COMPOSER_PACKAGES_PATH_REPOSITORY_MISSING');
    }

    private function packagesDirectoryPath(string $projectRoot, string $packagesDirName): string
    {
        $projectRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR);
        $packagesDirName = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $packagesDirName), DIRECTORY_SEPARATOR);
        if ($projectRoot === '' || !is_dir($projectRoot)) {
            throw new \RuntimeException('OPUS_PROJECT_ROOT_MISSING: ' . $projectRoot);
        }
        if ($packagesDirName === '' || str_contains($packagesDirName, '..')) {
            throw new \RuntimeException('OPUS_PACKAGES_DIRECTORY_NAME_INVALID: ' . $packagesDirName);
        }

        $packagesDir = $projectRoot . DIRECTORY_SEPARATOR . $packagesDirName;
        if (!is_dir($packagesDir)) {
            throw new \RuntimeException('OPUS_PACKAGES_DIRECTORY_MISSING: ' . $packagesDir);
        }

        return $packagesDir;
    }

    /**
     * @return list<string>
     */
    private function packageDirectories(string $packagesDir): array
    {
        $items = scandir($packagesDir);
        if ($items === false) {
            throw new \RuntimeException('OPUS_PACKAGES_DIRECTORY_UNREADABLE: ' . $packagesDir);
        }

        $directories = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $packagesDir . DIRECTORY_SEPARATOR . $item;
            if (!is_dir($path)) {
                continue;
            }
            if (!is_file($path . DIRECTORY_SEPARATOR . 'composer.json')) {
                throw new \RuntimeException('OPUS_PACKAGES_DIRECTORY_PACKAGE_COMPOSER_MISSING: ' . $path);
            }
            $directories[] = $path;
        }

        sort($directories);

        return $directories;
    }
}
