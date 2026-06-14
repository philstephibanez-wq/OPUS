<?php
/**
 * OPUS optional package installer.
 *
 * Visibility: public CLI maintenance tool.
 * Role: install one official optional OPUS package into a target site directory without copying
 *       the OPUS framework core.
 * Arguments: --package=<slug> --target=<path> --opus-root=<path> [--dry-run] [--allow-existing-empty]
 * Returns: process exit code 0 on success, 1 on contract failure.
 * Side effects: creates the target package directory and writes opus-runtime.local.json unless --dry-run is used.
 * Errors: missing manifest, invalid license/core contract, duplicated framework, dirty package tree,
 *         missing shared OPUS core, unsafe target path or non-empty target.
 * Business contract: one shared OPUS core; zero silent fallback; no framework duplication per site.
 */
final class OpusPackageInstaller
{
    private const LICENSE_PROFILE = 'OPUS_SOURCE_AVAILABLE_FREE_NONCOMMERCIAL_COMMERCIAL_ROYALTIES';
    private const COPYRIGHT_HOLDER = 'Philippe Stéphane Ibanez';
    private const RUNTIME_CONTRACT = 'OPUS_SHARED_CORE_PACKAGE_RUNTIME';

    /** @var list<string> */
    private array $errors = [];

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        $args = $this->parseArgs($argv);
        $dryRun = array_key_exists('dry-run', $args);
        $allowExistingEmpty = array_key_exists('allow-existing-empty', $args);

        $packageSlug = $this->requiredArg($args, 'package');
        $target = $this->requiredArg($args, 'target');
        $opusRoot = $this->requiredArg($args, 'opus-root');

        if ($this->errors !== []) {
            return $this->finish(false);
        }

        $repoRoot = dirname(__DIR__);
        $packageDir = $repoRoot . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . $packageSlug;
        $manifestPath = $packageDir . DIRECTORY_SEPARATOR . 'opus-package.json';

        if (!is_file($manifestPath)) {
            $this->error('Package manifest not found: ' . $manifestPath);
            return $this->finish(false);
        }

        $manifest = $this->readManifest($manifestPath);
        if ($manifest === null) {
            return $this->finish(false);
        }

        $this->validateManifest($manifest, $packageSlug);
        $this->scanForbiddenArtifacts($packageDir);

        $sharedCore = $this->resolveSharedCore($opusRoot);
        $targetPath = $this->validateTarget($target, $allowExistingEmpty);

        if ($this->errors !== []) {
            return $this->finish(false);
        }

        $this->printPlan($manifest, $packageDir, $targetPath, $sharedCore, $dryRun);

        if ($dryRun) {
            return $this->finish(true);
        }

        if (!is_dir($targetPath) && !mkdir($targetPath, 0775)) {
            $this->error('Unable to create target directory: ' . $targetPath);
            return $this->finish(false);
        }

        $this->copyTree($packageDir, $targetPath);
        $this->writeRuntimeContract($targetPath, $manifest, $packageDir, $sharedCore);

        if ($this->errors !== []) {
            return $this->finish(false);
        }

        fwrite(STDOUT, "OPUS_PACKAGE_INSTALL_OK\n");
        return 0;
    }

    /** @param list<string> $argv @return array<string, string|true> */
    private function parseArgs(array $argv): array
    {
        $args = [];
        foreach (array_slice($argv, 1) as $arg) {
            if (!str_starts_with($arg, '--')) {
                $this->error('Invalid argument format: ' . $arg);
                continue;
            }
            $raw = substr($arg, 2);
            if (str_contains($raw, '=')) {
                [$key, $value] = explode('=', $raw, 2);
                $args[$key] = $value;
            } else {
                $args[$raw] = true;
            }
        }
        return $args;
    }

    /** @param array<string, string|true> $args */
    private function requiredArg(array $args, string $name): string
    {
        if (!isset($args[$name]) || !is_string($args[$name]) || trim($args[$name]) === '') {
            $this->error('Missing required argument --' . $name . '=...');
            return '';
        }
        return $args[$name];
    }

    /** @return array<string, mixed>|null */
    private function readManifest(string $manifestPath): ?array
    {
        $raw = file_get_contents($manifestPath);
        if ($raw === false) {
            $this->error('Unable to read manifest: ' . $manifestPath);
            return null;
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            $this->error('Invalid package manifest JSON: ' . json_last_error_msg());
            return null;
        }
        return $json;
    }

    /** @param array<string, mixed> $manifest */
    private function validateManifest(array $manifest, string $packageSlug): void
    {
        $this->requireEquals($manifest, 'package_slug', $packageSlug, 'manifest');
        $this->requireEquals($manifest, 'requires_opus_name', 'OPUS Framework', 'manifest');
        $this->requireEquals($manifest, 'license_profile', self::LICENSE_PROFILE, 'manifest');

        $core = $manifest['core_resolution'] ?? null;
        if (!is_array($core)) {
            $this->error('manifest.core_resolution must be an object');
        } else {
            $this->requireEquals($core, 'mode', 'shared_opus_core_required', 'core_resolution');
            $this->requireEquals($core, 'forbid_embedded_framework', true, 'core_resolution');
            $this->requireEquals($core, 'fail_if_core_missing', true, 'core_resolution');
            $this->requireEquals($core, 'fallback_allowed', false, 'core_resolution');
        }

        $license = $manifest['license'] ?? null;
        if (!is_array($license)) {
            $this->error('manifest.license must be an object');
        } else {
            $this->requireEquals($license, 'profile', self::LICENSE_PROFILE, 'license');
            $this->requireEquals($license, 'copyright_holder', self::COPYRIGHT_HOLDER, 'license');
            $this->requireEquals($license, 'commercial_use', 'paid_commercial_license_required', 'license');
            $this->requireEquals($license, 'commercial_royalties', 'required', 'license');
            $this->requireEquals($license, 'osi_open_source', false, 'license');
        }
    }

    /** @param array<string, mixed> $data */
    private function requireEquals(array $data, string $key, mixed $expected, string $section): void
    {
        if (!array_key_exists($key, $data) || $data[$key] !== $expected) {
            $this->error($section . '.' . $key . ' must be ' . var_export($expected, true));
        }
    }

    private function resolveSharedCore(string $opusRoot): string
    {
        $root = realpath($opusRoot);
        if ($root === false || !is_dir($root)) {
            $this->error('OPUS root not found: ' . $opusRoot);
            return '';
        }
        $framework = $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus';
        if (!is_dir($framework)) {
            $this->error('Shared OPUS core not found: ' . $framework);
            return '';
        }
        return $framework;
    }

    private function validateTarget(string $target, bool $allowExistingEmpty): string
    {
        $parent = dirname($target);
        if (!is_dir($parent)) {
            $this->error('Target parent directory not found: ' . $parent);
            return $target;
        }
        if (is_dir($target)) {
            if (!$allowExistingEmpty) {
                $this->error('Target already exists. Use --allow-existing-empty only for an existing empty directory: ' . $target);
                return $target;
            }
            $items = array_diff(scandir($target) ?: [], ['.', '..']);
            if ($items !== []) {
                $this->error('Target directory is not empty: ' . $target);
            }
        }
        return $target;
    }

    private function scanForbiddenArtifacts(string $packageDir): void
    {
        if (is_dir($packageDir . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus')) {
            $this->error('Package contains duplicated framework/Opus');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($packageDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $entry) {
            $name = strtolower($entry->getFilename());
            $relative = substr($entry->getPathname(), strlen($packageDir) + 1);
            if ($entry->isDir() && in_array($name, ['vendor', 'cache', 'tmp', '.cache'], true)) {
                $this->error('Forbidden package directory: ' . $relative);
                continue;
            }
            if (!$entry->isFile()) {
                continue;
            }
            if (str_ends_with($name, '.twig')) {
                $this->error('Active Twig template forbidden: ' . $relative);
            }
            if (preg_match('/\.(bak|old|orig|tmp|swp)$/i', $name) === 1) {
                $this->error('Backup/temp artifact forbidden: ' . $relative);
            }
            if (preg_match('/(^|[._-])legacy([._-]|$)/i', $name) === 1) {
                $this->error('Legacy artifact forbidden: ' . $relative);
            }
            if (in_array($name, ['.env', '.env.local', 'secrets.json', 'secret.json'], true)) {
                $this->error('Secret-like file forbidden: ' . $relative);
            }
        }
    }

    /** @param array<string, mixed> $manifest */
    private function printPlan(array $manifest, string $packageDir, string $targetPath, string $sharedCore, bool $dryRun): void
    {
        fwrite(STDOUT, ($dryRun ? "OPUS_PACKAGE_INSTALL_DRY_RUN\n" : "OPUS_PACKAGE_INSTALL_PLAN\n"));
        fwrite(STDOUT, 'PACKAGE=' . (string) $manifest['package_slug'] . "\n");
        fwrite(STDOUT, 'SOURCE=' . $packageDir . "\n");
        fwrite(STDOUT, 'TARGET=' . $targetPath . "\n");
        fwrite(STDOUT, 'SHARED_CORE=' . $sharedCore . "\n");
    }

    private function copyTree(string $source, string $target): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $entry) {
            $relative = substr($entry->getPathname(), strlen($source) + 1);
            $destination = $target . DIRECTORY_SEPARATOR . $relative;
            if ($entry->isDir()) {
                if (!is_dir($destination) && !mkdir($destination, 0775)) {
                    $this->error('Unable to create directory: ' . $destination);
                }
                continue;
            }
            if (!copy($entry->getPathname(), $destination)) {
                $this->error('Unable to copy file: ' . $relative);
            }
        }
    }

    /** @param array<string, mixed> $manifest */
    private function writeRuntimeContract(string $targetPath, array $manifest, string $packageDir, string $sharedCore): void
    {
        $contract = [
            'runtime_contract' => self::RUNTIME_CONTRACT,
            'package_slug' => $manifest['package_slug'] ?? '',
            'package_name' => $manifest['package_name'] ?? '',
            'installed_from' => $packageDir,
            'opus_framework' => $sharedCore,
            'fallback_allowed' => false,
            'framework_duplication_allowed' => false,
            'created_at_utc' => gmdate('c'),
        ];

        $json = json_encode($contract, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            $this->error('Unable to encode runtime contract JSON');
            return;
        }
        if (file_put_contents($targetPath . DIRECTORY_SEPARATOR . 'opus-runtime.local.json', $json . "\n") === false) {
            $this->error('Unable to write opus-runtime.local.json');
        }
    }

    private function error(string $message): void
    {
        $this->errors[] = $message;
    }

    private function finish(bool $successWhenNoErrors): int
    {
        if ($this->errors === []) {
            return $successWhenNoErrors ? 0 : 1;
        }
        fwrite(STDERR, "OPUS_PACKAGE_INSTALL_FAILED\n");
        foreach ($this->errors as $error) {
            fwrite(STDERR, '- ' . $error . "\n");
        }
        return 1;
    }
}

exit((new OpusPackageInstaller())->run($argv));
