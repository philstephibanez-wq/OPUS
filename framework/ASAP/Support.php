<?php

declare(strict_types=1);

namespace ASAP;

/**
 * PUBLIC LEGACY COMPATIBILITY SHIM
 *
 * Role:
 *   Restore the small `ASAP\Support` utility surface used by legacy ASAP code.
 *
 * Contract:
 *   Pure helpers only. No global state, no IO side effects and no hidden fallback.
 *
 * Since:
 *   P112O
 */
final class Support
{
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function normalizePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '') {
            throw Exception::because('ASAP_SUPPORT_PATH_EMPTY');
        }

        $prefix = '';
        if (preg_match('/^[A-Za-z]:/', $path) === 1) {
            $prefix = substr($path, 0, 2);
            $path = substr($path, 2);
        }

        $absolute = str_starts_with($path, '/');
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    throw Exception::because('ASAP_SUPPORT_PATH_TRAVERSAL_OUTSIDE_ROOT', $path);
                }

                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        $normalized = implode('/', $segments);

        if ($absolute) {
            $normalized = '/' . $normalized;
        }

        if ($prefix !== '') {
            $normalized = $prefix . $normalized;
        }

        return $normalized === '' ? ($absolute ? '/' : '.') : $normalized;
    }

    public static function startsWith(string $value, string $prefix): bool
    {
        return str_starts_with($value, $prefix);
    }

    public static function trimSlashes(string $value): string
    {
        return trim($value, '/\\');
    }
}
