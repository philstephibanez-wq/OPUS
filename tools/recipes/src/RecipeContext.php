<?php

declare(strict_types=1);

namespace Opus\Recipe;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Provide shared filesystem, assertion and autoload helpers to Opus recipes.
 *
 * Responsibility:
 *   Centralize path resolution, sandbox allocation and explicit recipe
 *   assertions so individual recipes stay small and deterministic.
 *
 * Contract:
 *   The context never guesses the project root silently. It receives the root
 *   from the runner and every path check fails with an explicit code.
 */
final class RecipeContext
{
    /** @var string[] */
    private array $diagnostics = [];

    public function __construct(
        private readonly string $rootPath,
        private readonly string $runId,
        private readonly string $runtimePath
    ) {
    }

    /** PUBLIC API: project root absolute path. */
    public function rootPath(): string
    {
        return $this->rootPath;
    }

    /** PUBLIC API: unique recipe run identifier. */
    public function runId(): string
    {
        return $this->runId;
    }

    /** PUBLIC API: root runtime directory for reports and sandboxes. */
    public function runtimePath(): string
    {
        return $this->runtimePath;
    }

    /**
     * PUBLIC API
     *
     * @param string $relative Relative path from project root.
     *
     * @return string Absolute normalized path.
     */
    public function path(string $relative): string
    {
        return $this->rootPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    }

    /**
     * PUBLIC API
     *
     * @param string $name Sandbox name.
     *
     * @return string Absolute sandbox path.
     */
    public function sandbox(string $name): string
    {
        $path = $this->runtimePath . DIRECTORY_SEPARATOR . 'sandboxes' . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw RecipeAssertionFailedException::because('OPUS_RECIPE_SANDBOX_CREATE_FAILED', $path);
        }

        return $path;
    }

    /** PUBLIC API: register the official Opus classmap-cache autoloader for recipe checks. */
    public function registerOpusAutoload(): void
    {
        $root = $this->rootPath;
        $autoloadCache = $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus'
            . DIRECTORY_SEPARATOR . 'Autoload' . DIRECTORY_SEPARATOR . 'AutoloadCache.php';
        $classMapBuilder = $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus'
            . DIRECTORY_SEPARATOR . 'Autoload' . DIRECTORY_SEPARATOR . 'ClassMapBuilder.php';

        if (is_file($autoloadCache) && is_file($classMapBuilder)) {
            require_once $classMapBuilder;
            require_once $autoloadCache;

            $cacheFile = \ASAP\Autoload\AutoloadCache::defaultCacheFile($root);
            if (!is_file($cacheFile)) {
                $builder = new \ASAP\Autoload\ClassMapBuilder();
                $builder->write($builder->build($root), $cacheFile);
            }

            (new \ASAP\Autoload\AutoloadCache($root, $cacheFile))->register();
            return;
        }

        throw RecipeAssertionFailedException::because('OPUS_AUTOLOADER_CACHE_RUNTIME_MISSING', $autoloadCache);
    }

    /**
     * PUBLIC API
     *
     * @param bool $condition Assertion condition.
     * @param string $code Stable failure code.
     * @param string $detail Optional diagnostic detail.
     */
    public function assert(bool $condition, string $code, string $detail = ''): void
    {
        if (!$condition) {
            throw RecipeAssertionFailedException::because($code, $detail);
        }
    }

    /** PUBLIC API: assert that a file exists. */
    public function assertFile(string $relative): void
    {
        $this->assert(is_file($this->path($relative)), 'OPUS_RECIPE_REQUIRED_FILE_MISSING', $relative);
    }

    /** PUBLIC API: assert that a directory exists. */
    public function assertDirectory(string $relative): void
    {
        $this->assert(is_dir($this->path($relative)), 'OPUS_RECIPE_REQUIRED_DIRECTORY_MISSING', $relative);
    }

    /** PUBLIC API: add non-fatal explicit diagnostic message. */
    public function diagnostic(string $message): void
    {
        $this->diagnostics[] = $message;
    }

    /** @return string[] */
    public function pullDiagnostics(): array
    {
        $diagnostics = $this->diagnostics;
        $this->diagnostics = [];

        return $diagnostics;
    }

    /**
     * PUBLIC API
     *
     * @param string[] $directories Relative directories to scan.
     *
     * @return string[] Absolute PHP file paths.
     */
    public function phpFiles(array $directories): array
    {
        $files = [];
        foreach ($directories as $directory) {
            $absolute = $this->path($directory);
            if (!is_dir($absolute)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absolute, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $item) {
                if (!$item instanceof SplFileInfo || !$item->isFile() || strtolower($item->getExtension()) !== 'php') {
                    continue;
                }

                $normalized = str_replace('\\', '/', $item->getPathname());
                if (str_contains($normalized, '/vendor/') || str_contains($normalized, '/var/')) {
                    continue;
                }

                $files[] = $item->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * PUBLIC API
     *
     * @param string $absolutePath Absolute path to turn into a root-relative path.
     */
    public function relativePath(string $absolutePath): string
    {
        $relative = str_replace('\\', '/', $absolutePath);
        $root = rtrim(str_replace('\\', '/', $this->rootPath), '/') . '/';
        if (str_starts_with($relative, $root)) {
            return substr($relative, strlen($root));
        }

        return $relative;
    }

    /**
     * PUBLIC API
     *
     * @param string $command Command to execute.
     * @param string $failureCode Stable failure code.
     *
     * @return string[] Process output.
     */
    public function runCommand(string $command, string $failureCode): array
    {
        $output = [];
        $code = 0;
        exec($command . ' 2>&1', $output, $code);
        if ($code !== 0) {
            throw RecipeAssertionFailedException::because($failureCode, implode(' ', $output));
        }

        return $output;
    }

    /** PUBLIC API: remove a path recursively inside a sandbox. */
    public function removePath(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($path);
    }
}
