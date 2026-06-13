<?php

declare(strict_types=1);

namespace Opus\Autoload;

use RuntimeException;

/*
 * OPUS_REFBOOK:
 *   domain: AUTOLOAD
 *   role: Class AutoloadCache belongs to the AUTOLOAD Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the AUTOLOAD domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - autoload-overview
 *   diagrams:
 *     - autoload-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Register the official Opus classmap cache autoloader.
 *
 * Responsibility:
 *   Load a generated immutable PHP class map and resolve Opus classes from it.
 *
 * Contract:
 *   No filesystem guessing is allowed at runtime. If the cache is missing,
 *   invalid, or points to a missing file, the failure is explicit.
 */
final class AutoloadCache
{
    private const SCHEMA = 'OPUS_AUTOLOAD_CLASSMAP_V1';

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
            . 'opus_classmap.php';
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
            throw new RuntimeException('OPUS_AUTOLOAD_CACHE_FILE_MISSING: ' . $file);
        }

        $map = require $file;
        if (!is_array($map)) {
            throw new RuntimeException('OPUS_AUTOLOAD_CACHE_INVALID: ' . $file);
        }

        if (($map['schema'] ?? '') !== self::SCHEMA) {
            throw new RuntimeException('OPUS_AUTOLOAD_CACHE_SCHEMA_INVALID: ' . $file);
        }

        if (!isset($map['classes']) || !is_array($map['classes']) || $map['classes'] === []) {
            throw new RuntimeException('OPUS_AUTOLOAD_CACHE_CLASSES_EMPTY: ' . $file);
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
                    throw new RuntimeException('OPUS_AUTOLOAD_CACHE_TARGET_MISSING: ' . $class . ' :: ' . $path);
                }

                require_once $path;
            },
            true,
            $prepend
        );
    }
}
