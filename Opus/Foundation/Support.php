<?php
declare(strict_types=1);

namespace Opus\Foundation;

/**
 * Shared support helpers for the modern OPUS foundation layer.
 *
 * Centralizes small framework-level helpers used by the runtime while keeping application rendering and business logic out of utility code.
 */
final class Support
 implements SupportInterface {
    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        return rtrim($path, '/');
    }

    public static function trimSlashes(string $path): string
    {
        return trim($path, '/');
    }

    public static function startsWith(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
