<?php
declare(strict_types=1);

namespace Opus\Application\Package;

/**
 * Discovers Composer-installed OPUS application packages from vendor metadata.
 */
final class ComposerApplicationPackageRepository
{
    private string $projectRoot;
    private ApplicationPackageContract $contract;

    public function __construct(string $projectRoot, ?ApplicationPackageContract $contract = null)
    {
        $projectRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR);
        if ($projectRoot === '') {
            throw new \InvalidArgumentException('OPUS_APP_PACKAGE_PROJECT_ROOT_EMPTY');
        }

        $this->projectRoot = $projectRoot;
        $this->contract = $contract ?? new ApplicationPackageContract();
    }

    /** @return list<ApplicationPackageManifest> */
    public function discover(): array
    {
        $installedPath = $this->projectRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR . 'installed.php';
        if (!is_file($installedPath)) {
            throw new \RuntimeException('OPUS_APP_PACKAGE_COMPOSER_INSTALLED_MISSING: ' . $installedPath);
        }

        $installed = require $installedPath;
        if (!is_array($installed)) {
            throw new \RuntimeException('OPUS_APP_PACKAGE_COMPOSER_INSTALLED_INVALID: ' . $installedPath);
        }

        $versions = isset($installed['versions']) && is_array($installed['versions']) ? $installed['versions'] : $installed;
        $manifests = [];
        foreach ($versions as $packageName => $package) {
            if (!is_array($package)) {
                continue;
            }
            if ((string) ($package['type'] ?? '') !== ApplicationPackageContract::COMPOSER_TYPE) {
                continue;
            }
            $installPath = $package['install_path'] ?? null;
            if (!is_string($installPath) || trim($installPath) === '') {
                throw new \RuntimeException('OPUS_APP_PACKAGE_INSTALL_PATH_MISSING: ' . (string) $packageName);
            }

            $manifests[] = $this->contract->validatePackageDirectory($installPath);
        }

        usort($manifests, static fn (ApplicationPackageManifest $a, ApplicationPackageManifest $b): int => $a->packageName() <=> $b->packageName());

        return $manifests;
    }
}
