<?php

declare(strict_types=1);

namespace Opus\Autoload;

use Opus\Log\RuntimeLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/*
 * OPUS_REFBOOK:
 *   domain: AUTOLOAD
 *   role: Autoloader is the official OPUS framework runtime autoloader.
 *   contract:
 *     - called by OPUS root index.php
 *     - rebuilds the classmap cache when missing or stale
 *     - writes runtime logs to OPUS var/logs
 *     - enforces OPUS var strictness: cache/logs only
 *   examples:
 *     - opus-index-entrypoint
 *   diagrams:
 *     - autoload-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Boot the OPUS framework autoload layer from the single product entrypoint.
 *
 * Responsibility:
 *   Validate the runtime storage contract, rebuild the classmap cache when
 *   necessary and register the cache-backed autoload resolver.
 *
 * Contract:
 *   `var/cache` and `var/logs` are the only authorized OPUS runtime storage
 *   directories. Missing or stale autoload cache is rebuilt explicitly. Any
 *   contract violation is fatal and must not be hidden by a fallback loader.
 */
final class Autoloader
{
    private const ALLOWED_VAR_ENTRIES = ['cache', 'logs'];

    private readonly RuntimeLogger $logger;

    public function __construct(private readonly string $projectRoot)
    {
        $this->logger = new RuntimeLogger($projectRoot);
    }

    /**
     * PUBLIC API
     *
     * @return array{ok:bool,root:string,cache_file:string,log_file:string,class_count:int,rebuild:bool}
     */
    public static function boot(string $projectRoot): array
    {
        return (new self($projectRoot))->register();
    }

    /**
     * PUBLIC API
     *
     * @return array{ok:bool,root:string,cache_file:string,log_file:string,class_count:int,rebuild:bool}
     */
    public function register(): array
    {
        $root = $this->normalizeRoot($this->projectRoot);
        $this->ensureRuntimeDirectories($root);
        $this->assertStrictVarContract($root);

        $cacheFile = AutoloadCache::defaultCacheFile($root);
        $rebuilt = $this->ensureAutoloadCache($root, $cacheFile);

        $cache = new AutoloadCache($root, $cacheFile);
        $map = $cache->load();
        $cache->register(true);

        $classCount = count((array)($map['classes'] ?? []));
        $this->logger->info('OPUS_AUTOLOAD_REGISTERED', [
            'cache_file' => $this->relativePath($root, $cacheFile),
            'class_count' => $classCount,
            'rebuild' => $rebuilt,
        ]);

        return [
            'ok' => true,
            'root' => $root,
            'cache_file' => $cacheFile,
            'log_file' => RuntimeLogger::defaultLogFile($root),
            'class_count' => $classCount,
            'rebuild' => $rebuilt,
        ];
    }

    private function ensureRuntimeDirectories(string $root): void
    {
        foreach (['var', 'var/cache', 'var/logs'] as $relativeDir) {
            $dir = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('OPUS_RUNTIME_DIR_CREATE_FAILED: ' . $relativeDir);
            }
        }
    }

    private function assertStrictVarContract(string $root): void
    {
        $varRoot = $root . DIRECTORY_SEPARATOR . 'var';
        $entries = scandir($varRoot);
        if (!is_array($entries)) {
            throw new RuntimeException('OPUS_VAR_SCAN_FAILED: ' . $varRoot);
        }

        $forbidden = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (!in_array($entry, self::ALLOWED_VAR_ENTRIES, true)) {
                $forbidden[] = $entry;
                continue;
            }

            $path = $varRoot . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($path)) {
                $forbidden[] = $entry;
            }
        }

        if ($forbidden !== []) {
            sort($forbidden);
            throw new RuntimeException('OPUS_VAR_CONTRACT_FORBIDDEN_ENTRIES: ' . implode(', ', $forbidden));
        }
    }

    private function ensureAutoloadCache(string $root, string $cacheFile): bool
    {
        $needsRebuild = !is_file($cacheFile) || $this->isCacheStale($root, $cacheFile);
        if (!$needsRebuild) {
            $this->logger->info('OPUS_AUTOLOAD_CACHE_FRESH', [
                'cache_file' => $this->relativePath($root, $cacheFile),
            ]);
            return false;
        }

        $builder = new ClassMapBuilder();
        $map = $builder->build($root);
        $builder->write($map, $cacheFile);

        $this->logger->info('OPUS_AUTOLOAD_CACHE_REBUILT', [
            'cache_file' => $this->relativePath($root, $cacheFile),
            'class_count' => (int)($map['class_count'] ?? 0),
            'file_count' => (int)($map['file_count'] ?? 0),
        ]);

        return true;
    }

    private function isCacheStale(string $root, string $cacheFile): bool
    {
        $cacheMtime = filemtime($cacheFile);
        if ($cacheMtime === false) {
            return true;
        }

        foreach ($this->frameworkPhpFiles($root) as $file) {
            $mtime = filemtime($file);
            if ($mtime === false || $mtime > $cacheMtime) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] */
    private function frameworkPhpFiles(string $root): array
    {
        $sourceRoot = $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus';
        if (!is_dir($sourceRoot)) {
            throw new RuntimeException('OPUS_AUTOLOAD_SOURCE_ROOT_MISSING: ' . $sourceRoot);
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot));
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            if (strtolower($fileInfo->getExtension()) !== 'php') {
                continue;
            }

            $files[] = $fileInfo->getPathname();
        }

        sort($files);
        return $files;
    }

    private function normalizeRoot(string $path): string
    {
        $real = realpath($path);
        if ($real === false) {
            throw new RuntimeException('OPUS_RUNTIME_ROOT_NOT_FOUND: ' . $path);
        }

        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    private function relativePath(string $root, string $path): string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }
}
