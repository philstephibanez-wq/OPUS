<?php
declare(strict_types=1);

namespace Opus\Owasys;

use RuntimeException;
use ZipArchive;

/**
 * Exports a clean OPUS application site as a reproducible OWASYS ZIP deliverable.
 *
 * Export contract:
 * - source site must already be a valid OPUS site tree;
 * - export ZIP must never be written inside the source site;
 * - runtime residue is excluded from the archive;
 * - MANIFEST.json is generated inside the ZIP without local absolute paths.
 */
final class ApplicationExporter
{
    private const MANIFEST_CONTRACT = 'OWASYS_APPLICATION_EXPORT_MANIFEST_V1';
    private const SITE_CONTRACT = 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL';

    /** @var list<string> */
    private const REQUIRED_SITE_PATHS = [
        'config',
        'config/site.json',
        'config/routes.json',
        'application',
        'application/default',
        'application/default/acl',
        'application/default/helpers',
        'application/default/css',
        'application/default/javascript',
        'application/default/local',
        'application/default/models',
        'application/default/templates',
        'application/default/views',
        'www',
        'www/index.php',
        'www/asset',
        'www/asset/css',
        'www/asset/js',
        'www/asset/themes',
    ];

    /** @var list<string> */
    private const EXCLUDED_EXACT_PATHS = [
        '.git',
        '.idea',
        '.vscode',
        'vendor',
        'node_modules',
        'var/cache',
        'var/tmp',
        'var/log',
        'var/logs',
        'var/profiler',
    ];

    /** @var list<string> */
    private const EXCLUDED_SUFFIXES = [
        '.zip',
        '.bak',
        '.tmp',
        '.sqlite',
        '.sqlite-shm',
        '.sqlite-wal',
        '.log',
    ];

    public function __construct(private readonly string $opusRoot)
    {
    }

    /**
     * Exports a site and returns a normalized summary.
     *
     * @return array<string,mixed>
     */
    public function export(string $siteId, string $outputZip, bool $overwrite = false): array
    {
        $this->assertZipAvailable();
        $this->assertSiteId($siteId);

        $siteRoot = 'sites/' . $siteId;
        $absoluteSiteRoot = $this->absolutePath($siteRoot);
        $this->assertValidSite($siteId, $siteRoot, $absoluteSiteRoot);

        $absoluteOutput = $this->prepareOutputZip($outputZip, $absoluteSiteRoot, $overwrite);
        $relativeOutput = $this->relativePathFromRoot($absoluteOutput);

        $files = $this->collectFiles($absoluteSiteRoot);
        $manifest = $this->buildManifest($siteId, $siteRoot, $absoluteSiteRoot, $files);

        $zip = new ZipArchive();
        $flags = $overwrite ? ZipArchive::CREATE | ZipArchive::OVERWRITE : ZipArchive::CREATE;
        if ($zip->open($absoluteOutput, $flags) !== true) {
            throw new RuntimeException('OWASYS_EXPORT_ZIP_OPEN_FAILED: ' . $relativeOutput);
        }

        foreach ($files as $file) {
            $source = $absoluteSiteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file['path']);
            if (!$zip->addFile($source, $file['path'])) {
                $zip->close();
                throw new RuntimeException('OWASYS_EXPORT_ZIP_ADD_FILE_FAILED: ' . $file['path']);
            }
        }

        $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($manifestJson)) {
            $zip->close();
            throw new RuntimeException('OWASYS_EXPORT_MANIFEST_JSON_ENCODE_FAILED');
        }

        if (!$zip->addFromString('MANIFEST.json', $manifestJson . "\n")) {
            $zip->close();
            throw new RuntimeException('OWASYS_EXPORT_MANIFEST_ADD_FAILED');
        }

        if (!$zip->close()) {
            throw new RuntimeException('OWASYS_EXPORT_ZIP_CLOSE_FAILED: ' . $relativeOutput);
        }

        clearstatcache(true, $absoluteOutput);

        return [
            'contract' => self::MANIFEST_CONTRACT,
            'site_id' => $siteId,
            'site_root' => $siteRoot,
            'output_zip' => $relativeOutput,
            'manifest_path' => 'MANIFEST.json',
            'files' => count($files),
            'bytes' => is_file($absoluteOutput) ? filesize($absoluteOutput) : 0,
            'excluded_policy' => [
                'runtime_cache' => 'excluded',
                'runtime_tmp' => 'excluded',
                'runtime_logs' => 'excluded',
                'runtime_sqlite' => 'excluded',
                'local_absolute_paths' => 'forbidden',
            ],
        ];
    }

    private function assertZipAvailable(): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('OWASYS_EXPORT_ZIP_EXTENSION_MISSING');
        }
    }

    private function assertSiteId(string $siteId): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $siteId) !== 1) {
            throw new RuntimeException('OWASYS_EXPORT_SITE_ID_INVALID: ' . $siteId);
        }
    }

    private function assertValidSite(string $siteId, string $siteRoot, string $absoluteSiteRoot): void
    {
        if (!is_dir($absoluteSiteRoot)) {
            throw new RuntimeException('OWASYS_EXPORT_SITE_NOT_FOUND: ' . $siteRoot);
        }

        foreach (self::REQUIRED_SITE_PATHS as $relative) {
            $path = $absoluteSiteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (!file_exists($path)) {
                throw new RuntimeException('OWASYS_EXPORT_SITE_REQUIRED_PATH_MISSING: ' . $relative);
            }
        }

        $siteConfig = json_decode((string) file_get_contents($absoluteSiteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json'), true);
        if (!is_array($siteConfig) || ($siteConfig['contract'] ?? null) !== self::SITE_CONTRACT) {
            throw new RuntimeException('OWASYS_EXPORT_SITE_CONTRACT_INVALID: ' . $siteId);
        }
    }

    private function prepareOutputZip(string $outputZip, string $absoluteSiteRoot, bool $overwrite): string
    {
        $outputZip = trim(str_replace('\\', '/', $outputZip));
        if ($outputZip === '') {
            throw new RuntimeException('OWASYS_EXPORT_OUTPUT_ZIP_REQUIRED');
        }
        if (!str_ends_with(strtolower($outputZip), '.zip')) {
            throw new RuntimeException('OWASYS_EXPORT_OUTPUT_EXTENSION_INVALID: ' . $outputZip);
        }

        $absoluteOutput = $this->isAbsolutePath($outputZip) ? $outputZip : $this->absolutePath($outputZip);
        $absoluteOutput = str_replace('/', DIRECTORY_SEPARATOR, $absoluteOutput);
        $parent = dirname($absoluteOutput);

        if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
            throw new RuntimeException('OWASYS_EXPORT_OUTPUT_DIR_CREATE_FAILED: ' . $this->relativePathFromRoot($parent));
        }

        $parentReal = realpath($parent);
        $siteRootReal = realpath($absoluteSiteRoot);
        if ($parentReal === false || $siteRootReal === false) {
            throw new RuntimeException('OWASYS_EXPORT_REALPATH_FAILED');
        }

        $normalizedParent = str_replace('\\', '/', $parentReal);
        $normalizedSiteRoot = rtrim(str_replace('\\', '/', $siteRootReal), '/');
        if ($normalizedParent === $normalizedSiteRoot || str_starts_with($normalizedParent . '/', $normalizedSiteRoot . '/')) {
            throw new RuntimeException('OWASYS_EXPORT_OUTPUT_INSIDE_SITE_FORBIDDEN');
        }

        if (file_exists($absoluteOutput) && !$overwrite) {
            throw new RuntimeException('OWASYS_EXPORT_OUTPUT_ALREADY_EXISTS: ' . $this->relativePathFromRoot($absoluteOutput));
        }

        return $absoluteOutput;
    }

    /**
     * @return list<array{path:string,bytes:int,sha256:string}>
     */
    private function collectFiles(string $absoluteSiteRoot): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absoluteSiteRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $absolutePath = $item->getPathname();
            $relativePath = $this->relativePathFrom($absoluteSiteRoot, $absolutePath);
            if ($this->isExcluded($relativePath)) {
                continue;
            }

            $hash = hash_file('sha256', $absolutePath);
            if (!is_string($hash)) {
                throw new RuntimeException('OWASYS_EXPORT_FILE_HASH_FAILED: ' . $relativePath);
            }

            $files[] = [
                'path' => $relativePath,
                'bytes' => (int) $item->getSize(),
                'sha256' => $hash,
            ];
        }

        usort($files, static fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

        return $files;
    }

    private function isExcluded(string $relativePath): bool
    {
        $path = trim(str_replace('\\', '/', $relativePath), '/');
        foreach (self::EXCLUDED_EXACT_PATHS as $excluded) {
            if ($path === $excluded || str_starts_with($path, $excluded . '/')) {
                return true;
            }
        }

        foreach (self::EXCLUDED_SUFFIXES as $suffix) {
            if (str_ends_with(strtolower($path), $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{path:string,bytes:int,sha256:string}> $files
     * @return array<string,mixed>
     */
    private function buildManifest(string $siteId, string $siteRoot, string $absoluteSiteRoot, array $files): array
    {
        $siteConfig = json_decode((string) file_get_contents($absoluteSiteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json'), true);
        $creationManifestFile = $absoluteSiteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'owasys-creation-manifest.json';
        $creationManifest = is_file($creationManifestFile) ? json_decode((string) file_get_contents($creationManifestFile), true) : [];
        $composer = json_decode((string) @file_get_contents($this->absolutePath('composer.json')), true);

        return [
            'contract' => self::MANIFEST_CONTRACT,
            'application_id' => $siteId,
            'application_name' => is_array($siteConfig) ? (string) ($siteConfig['site_name'] ?? $siteId) : $siteId,
            'opus_version' => is_array($composer) ? (string) ($composer['version'] ?? '') : '',
            'site_contract' => self::SITE_CONTRACT,
            'generated_at' => gmdate('c'),
            'source_blueprint' => is_array($creationManifest) ? (string) ($creationManifest['blueprint'] ?? '') : '',
            'validation_status' => 'ok',
            'site_root' => $siteRoot,
            'files' => $files,
            'excluded' => [
                'vendor/**',
                'var/cache/**',
                'var/tmp/**',
                'var/log/**',
                'var/profiler/**',
                'var/registry/*.sqlite',
                '*.zip',
                '*.bak',
                '*.tmp',
                '*.log',
            ],
        ];
    }

    private function absolutePath(string $relativePath): string
    {
        return rtrim($this->opusRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($relativePath, '/\\'));
    }

    private function relativePathFrom(string $root, string $absolutePath): string
    {
        $root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/') . '/';
        $path = str_replace('\\', '/', realpath($absolutePath) ?: $absolutePath);
        if (!str_starts_with($path, $root)) {
            throw new RuntimeException('OWASYS_EXPORT_PATH_OUTSIDE_SITE: ' . $path);
        }
        return substr($path, strlen($root));
    }

    private function relativePathFromRoot(string $absolutePath): string
    {
        $root = rtrim(str_replace('\\', '/', realpath($this->opusRoot) ?: $this->opusRoot), '/') . '/';
        $path = str_replace('\\', '/', $absolutePath);
        $real = realpath($absolutePath);
        if ($real !== false) {
            $path = str_replace('\\', '/', $real);
        }
        if (str_starts_with($path, $root)) {
            return substr($path, strlen($root));
        }
        return $path;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path) === 1;
    }
}
