<?php

declare(strict_types=1);

namespace ASAP\Autoload;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Build the official ASAP classmap cache.
 *
 * Responsibility:
 *   Scan framework/Asap PHP files, extract declared symbols and write a stable
 *   class => file map consumable by AutoloadCache.
 *
 * Contract:
 *   Duplicate symbols are fatal. Missing namespaces/symbols are reported as
 *   diagnostics, never silently accepted as loadable classes.
 */
final class ClassMapBuilder
{
    private const SCHEMA = 'ASAP_AUTOLOAD_CLASSMAP_V1';

    /**
     * PUBLIC API
     *
     * @return array<string,mixed>
     */
    public function build(string $projectRoot, ?string $sourceRoot = null): array
    {
        $projectRoot = $this->normalizeRoot($projectRoot);
        $sourceRoot = $sourceRoot === null
            ? $projectRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap'
            : $this->normalizeRoot($sourceRoot);

        if (!is_dir($sourceRoot)) {
            throw new RuntimeException('ASAP_AUTOLOAD_SOURCE_ROOT_MISSING: ' . $sourceRoot);
        }

        $classes = [];
        $duplicates = [];
        $skipped = [];
        $files = $this->phpFiles($sourceRoot);

        foreach ($files as $file) {
            $relative = $this->relativePath($projectRoot, $file);
            $content = (string)file_get_contents($file);
            $namespace = $this->namespaceOf($content);

            if ($namespace === null) {
                $skipped[] = ['file' => $relative, 'reason' => 'NO_NAMESPACE'];
                continue;
            }

            $symbols = $this->symbolsOf($content);
            if ($symbols === []) {
                $skipped[] = ['file' => $relative, 'reason' => 'NO_SYMBOL'];
                continue;
            }

            foreach ($symbols as $symbol) {
                $fqcn = $namespace . '\\' . $symbol;
                if (isset($classes[$fqcn])) {
                    $duplicates[$fqcn] ??= [$classes[$fqcn]];
                    $duplicates[$fqcn][] = $relative;
                    continue;
                }

                $classes[$fqcn] = $relative;
            }
        }

        ksort($classes);
        ksort($duplicates);

        return [
            'schema' => self::SCHEMA,
            'generated_at' => date('c'),
            'root' => str_replace('\\', '/', $projectRoot),
            'source_root' => str_replace('\\', '/', $sourceRoot),
            'class_count' => count($classes),
            'file_count' => count($files),
            'classes' => $classes,
            'duplicates' => $duplicates,
            'skipped' => $skipped,
        ];
    }

    /** PUBLIC API: write a PHP cache file. */
    public function write(array $map, string $targetFile): void
    {
        if (($map['schema'] ?? '') !== self::SCHEMA) {
            throw new RuntimeException('ASAP_AUTOLOAD_MAP_SCHEMA_INVALID');
        }

        if (($map['duplicates'] ?? []) !== []) {
            throw new RuntimeException('ASAP_AUTOLOAD_DUPLICATE_CLASSES: ' . implode(', ', array_keys((array)$map['duplicates'])));
        }

        $dir = dirname($targetFile);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('ASAP_AUTOLOAD_CACHE_DIR_CREATE_FAILED: ' . $dir);
        }

        $php = '<?php' . PHP_EOL . PHP_EOL
            . 'declare(strict_types=1);' . PHP_EOL . PHP_EOL
            . 'return ' . var_export($map, true) . ';' . PHP_EOL;

        if (file_put_contents($targetFile, $php) === false) {
            throw new RuntimeException('ASAP_AUTOLOAD_CACHE_WRITE_FAILED: ' . $targetFile);
        }
    }

    /** @return string[] */
    private function phpFiles(string $sourceRoot): array
    {
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

    private function namespaceOf(string $content): ?string
    {
        if (preg_match('/^\s*namespace\s+([^;]+);/m', $content, $match) !== 1) {
            return null;
        }

        return trim($match[1]);
    }

    /** @return string[] */
    private function symbolsOf(string $content): array
    {
        preg_match_all(
            '/^\s*(?:abstract\s+|final\s+)?(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/m',
            $content,
            $matches
        );

        return array_values(array_unique($matches[1] ?? []));
    }

    private function normalizeRoot(string $path): string
    {
        $real = realpath($path);
        if ($real === false) {
            throw new RuntimeException('ASAP_AUTOLOAD_PATH_NOT_FOUND: ' . $path);
        }

        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    private function relativePath(string $root, string $file): string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $file = str_replace('\\', '/', $file);

        if (!str_starts_with($file, $root)) {
            throw new RuntimeException('ASAP_AUTOLOAD_FILE_OUTSIDE_ROOT: ' . $file);
        }

        return substr($file, strlen($root));
    }
}
