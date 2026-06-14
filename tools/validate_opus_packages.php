<?php
/**
 * OPUS package validator.
 *
 * Visibility: public CLI maintenance tool.
 * Role: validate official optional OPUS package manifests and clean-deliverable gates.
 * Arguments: none. The script must be launched from the OPUS repository root or from any
 *            descendant directory; it resolves the repository root from its own location.
 * Returns: process exit code 0 when every package passes, 1 when at least one package fails.
 * Side effects: prints a deterministic report to STDOUT/STDERR; does not modify files.
 * Errors: invalid JSON, missing manifest fields, forbidden fallbacks, duplicated framework,
 *         Twig templates, legacy backups, caches, secrets or vendor dumps in package trees.
 * Business contract: zero silent fallback. A missing or invalid package contract is a hard error.
 */

final class OpusPackageValidator
{
    private const LICENSE_PROFILE = 'OPUS_SOURCE_AVAILABLE_FREE_NONCOMMERCIAL_COMMERCIAL_ROYALTIES';
    private const COPYRIGHT_HOLDER = 'Philippe Stéphane Ibanez';

    /** @var list<string> */
    private array $errors = [];

    public function run(): int
    {
        $root = dirname(__DIR__);
        $packagesRoot = $root . DIRECTORY_SEPARATOR . 'packages';

        if (!is_dir($packagesRoot)) {
            $this->error('Packages root not found: ' . $packagesRoot);
            return $this->finish();
        }

        $packageDirs = $this->discoverPackageDirs($packagesRoot);
        if ($packageDirs === []) {
            $this->error('No OPUS package manifest found under packages/.');
            return $this->finish();
        }

        foreach ($packageDirs as $packageDir) {
            $this->validatePackage($packageDir);
        }

        return $this->finish();
    }

    /**
     * Discover directories containing an OPUS package manifest.
     *
     * @return list<string>
     */
    private function discoverPackageDirs(string $packagesRoot): array
    {
        $dirs = [];
        $iterator = new DirectoryIterator($packagesRoot);

        foreach ($iterator as $entry) {
            if ($entry->isDot() || !$entry->isDir()) {
                continue;
            }

            $manifest = $entry->getPathname() . DIRECTORY_SEPARATOR . 'opus-package.json';
            if (is_file($manifest)) {
                $dirs[] = $entry->getPathname();
            }
        }

        sort($dirs);
        return $dirs;
    }

    private function validatePackage(string $packageDir): void
    {
        $manifestPath = $packageDir . DIRECTORY_SEPARATOR . 'opus-package.json';
        $label = basename($packageDir);

        $manifest = $this->readManifest($manifestPath, $label);
        if ($manifest === null) {
            return;
        }

        $this->requireString($manifest, 'package_name', $label);
        $this->requireString($manifest, 'package_slug', $label);
        $this->requireString($manifest, 'package_type', $label);
        $this->requireString($manifest, 'package_status', $label);
        $this->requireString($manifest, 'requires_opus_version', $label);
        $this->requireString($manifest, 'entrypoint', $label);
        $this->requireString($manifest, 'public_root', $label);
        $this->requireString($manifest, 'application_root', $label);

        $this->requireEquals($manifest, 'requires_opus_name', 'OPUS Framework', $label);
        $this->requireEquals($manifest, 'license_profile', self::LICENSE_PROFILE, $label);

        $this->validateCoreResolution($manifest, $label);
        $this->validateLicense($manifest, $label);
        $this->validateCleanGate($manifest, $label);
        $this->scanForbiddenArtifacts($packageDir, $label);
    }

    /**
     * Read and decode a package manifest.
     *
     * @return array<string, mixed>|null
     */
    private function readManifest(string $manifestPath, string $label): ?array
    {
        $raw = file_get_contents($manifestPath);
        if ($raw === false) {
            $this->error($label . ': unable to read opus-package.json');
            return null;
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            $this->error($label . ': invalid JSON manifest: ' . json_last_error_msg());
            return null;
        }

        return $json;
    }

    /** @param array<string, mixed> $manifest */
    private function validateCoreResolution(array $manifest, string $label): void
    {
        $core = $manifest['core_resolution'] ?? null;
        if (!is_array($core)) {
            $this->error($label . ': core_resolution must be an object');
            return;
        }

        $this->requireNestedEquals($core, 'mode', 'shared_opus_core_required', $label, 'core_resolution');
        $this->requireNestedEquals($core, 'forbid_embedded_framework', true, $label, 'core_resolution');
        $this->requireNestedEquals($core, 'fail_if_core_missing', true, $label, 'core_resolution');
        $this->requireNestedEquals($core, 'fallback_allowed', false, $label, 'core_resolution');
    }

    /** @param array<string, mixed> $manifest */
    private function validateLicense(array $manifest, string $label): void
    {
        $license = $manifest['license'] ?? null;
        if (!is_array($license)) {
            $this->error($label . ': license must be an object');
            return;
        }

        $this->requireNestedEquals($license, 'profile', self::LICENSE_PROFILE, $label, 'license');
        $this->requireNestedEquals($license, 'copyright_holder', self::COPYRIGHT_HOLDER, $label, 'license');
        $this->requireNestedEquals($license, 'commercial_use', 'paid_commercial_license_required', $label, 'license');
        $this->requireNestedEquals($license, 'commercial_royalties', 'required', $label, 'license');
        $this->requireNestedEquals($license, 'osi_open_source', false, $label, 'license');

        $requiredFiles = $license['legal_files_required_before_public_release'] ?? null;
        if (!is_array($requiredFiles) || count($requiredFiles) < 6) {
            $this->error($label . ': license.legal_files_required_before_public_release must list required legal files');
        }
    }

    /** @param array<string, mixed> $manifest */
    private function validateCleanGate(array $manifest, string $label): void
    {
        $gate = $manifest['clean_deliverable_gate'] ?? null;
        if (!is_array($gate)) {
            $this->error($label . ': clean_deliverable_gate must be an object');
            return;
        }

        foreach ([
            'active_twig_templates_allowed',
            'legacy_backups_allowed',
            'dead_css_overrides_allowed',
            'runtime_cache_allowed',
            'secrets_allowed',
            'duplicated_framework_allowed',
        ] as $key) {
            $this->requireNestedEquals($gate, $key, false, $label, 'clean_deliverable_gate');
        }
    }

    private function scanForbiddenArtifacts(string $packageDir, string $label): void
    {
        $duplicatedFramework = $packageDir . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus';
        if (is_dir($duplicatedFramework)) {
            $this->error($label . ': duplicated framework detected at framework/Opus/');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($packageDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $entry) {
            $path = $entry->getPathname();
            $relative = substr($path, strlen($packageDir) + 1);
            $name = strtolower($entry->getFilename());

            if ($entry->isDir() && in_array($name, ['vendor', 'cache', 'tmp', '.cache'], true)) {
                $this->error($label . ': forbidden directory in package: ' . $relative);
                continue;
            }

            if (!$entry->isFile()) {
                continue;
            }

            if (str_ends_with($name, '.twig')) {
                $this->error($label . ': active Twig template forbidden: ' . $relative);
            }

            if (preg_match('/\.(bak|old|orig|tmp|swp)$/i', $name) === 1) {
                $this->error($label . ': backup/temp artifact forbidden: ' . $relative);
            }

            if (preg_match('/(^|[._-])legacy([._-]|$)/i', $name) === 1) {
                $this->error($label . ': legacy artifact forbidden: ' . $relative);
            }

            if (in_array($name, ['.env', '.env.local', 'secrets.json', 'secret.json'], true)) {
                $this->error($label . ': secret-like file forbidden: ' . $relative);
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function requireString(array $data, string $key, string $label): void
    {
        if (!isset($data[$key]) || !is_string($data[$key]) || trim($data[$key]) === '') {
            $this->error($label . ': missing or invalid string field: ' . $key);
        }
    }

    /** @param array<string, mixed> $data */
    private function requireEquals(array $data, string $key, mixed $expected, string $label): void
    {
        if (!array_key_exists($key, $data) || $data[$key] !== $expected) {
            $this->error($label . ': field ' . $key . ' must be ' . var_export($expected, true));
        }
    }

    /** @param array<string, mixed> $data */
    private function requireNestedEquals(array $data, string $key, mixed $expected, string $label, string $section): void
    {
        if (!array_key_exists($key, $data) || $data[$key] !== $expected) {
            $this->error($label . ': ' . $section . '.' . $key . ' must be ' . var_export($expected, true));
        }
    }

    private function error(string $message): void
    {
        $this->errors[] = $message;
    }

    private function finish(): int
    {
        if ($this->errors === []) {
            fwrite(STDOUT, "OPUS_PACKAGE_VALIDATION_OK\n");
            return 0;
        }

        fwrite(STDERR, "OPUS_PACKAGE_VALIDATION_FAILED\n");
        foreach ($this->errors as $error) {
            fwrite(STDERR, '- ' . $error . "\n");
        }

        return 1;
    }
}

exit((new OpusPackageValidator())->run());
