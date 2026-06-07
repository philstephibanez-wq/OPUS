<?php

declare(strict_types=1);

namespace ASAP\Autoload;

use RuntimeException;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Register the official ASAP classmap cache autoloader.
 *
 * Responsibility:
 *   Load a generated immutable PHP class map and resolve ASAP classes from it.
 *
 * Contract:
 *   No filesystem guessing is allowed at runtime. If the cache is missing,
 *   invalid, or points to a missing file, the failure is explicit.
 */
final class AutoloadCache
{
    private const SCHEMA = 'ASAP_AUTOLOAD_CLASSMAP_V1';

    public function __construct(
        private readonly string $projectRoot,
        private readonly ?string $cacheFile = null
    ) {
    }

    public static function defaultCacheFile(string $projectRoot): string
    {
        return rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR
            . 'asap' . DIRECTORY_SEPARATOR . 'autoload' . DIRECTORY_SEPARATOR
            . 'asap_classmap.php';
    }

    /**
     * PUBLIC API
     *
     * @return array{schema:string,root:string,classes:array<string,string>}
     */
    public function load(): array
    {
        $file = $this->cacheFile ?? self::defaultCacheFile($this->projectRoot);
        if (!is_file($file)) {
            throw new RuntimeException('ASAP_AUTOLOAD_CACHE_FILE_MISSING: ' . $file);
        }

        $map = require $file;
        if (!is_array($map)) {
            throw new RuntimeException('ASAP_AUTOLOAD_CACHE_INVALID: ' . $file);
        }

        if (($map['schema'] ?? '') !== self::SCHEMA) {
            throw new RuntimeException('ASAP_AUTOLOAD_CACHE_SCHEMA_INVALID: ' . $file);
        }

        if (!isset($map['classes']) || !is_array($map['classes']) || $map['classes'] === []) {
            throw new RuntimeException('ASAP_AUTOLOAD_CACHE_CLASSES_EMPTY: ' . $file);
        }

        return $map;
    }

    /** PUBLIC API: register cache-backed autoload. */
    public function register(bool $prepend = true): void
    {
        $map = $this->load();
        $root = rtrim((string)($map['root'] ?? $this->projectRoot), '/\\');
        $classes = $map['classes'];

        spl_autoload_register(
            static function (string $class) use ($classes, $root): void {
                if (!isset($classes[$class])) {
                    return;
                }

                $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$classes[$class]);
                $path = $root . DIRECTORY_SEPARATOR . $relative;

                if (!is_file($path)) {
                    throw new RuntimeException('ASAP_AUTOLOAD_CACHE_TARGET_MISSING: ' . $class . ' :: ' . $path);
                }

                require_once $path;
            },
            true,
            $prepend
        );
    }
}
