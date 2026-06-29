<?php
declare(strict_types=1);

namespace Opus\Application\Package;

/**
 * Discovers source packages from the OPUS monorepo packages directory.
 */
final class PackagesDirectoryApplicationRepository
{
    private string $projectRoot;
    private string $packagesDirName;
    private PackagesDirectoryContract $contract;

    public function __construct(string $projectRoot, string $packagesDirName = PackagesDirectoryContract::DEFAULT_PACKAGES_DIR, ?PackagesDirectoryContract $contract = null)
    {
        $projectRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR);
        if ($projectRoot === '') {
            throw new \InvalidArgumentException('OPUS_PACKAGES_DIRECTORY_PROJECT_ROOT_EMPTY');
        }

        $this->projectRoot = $projectRoot;
        $this->packagesDirName = $packagesDirName;
        $this->contract = $contract ?? new PackagesDirectoryContract();
    }

    /**
     * @return list<ApplicationPackageManifest>
     */
    public function discover(): array
    {
        return $this->contract->validateProjectPackages($this->projectRoot, $this->packagesDirName);
    }
}
