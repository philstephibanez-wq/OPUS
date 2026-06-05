<?php

declare(strict_types=1);

namespace ASAP\HELPER;

/**
 * PUBLIC LEGACY-ALIGNED HELPER
 *
 * Role:
 *   Preserve the original ASAP `HELPER\Helper` domain.
 *
 * Responsibility:
 *   Provide deterministic presentation-safe helpers.
 *
 * Contract:
 *   Helpers transform only. They do not load data or decide rendering strategy.
 *
 * Since:
 *   P112D4C
 */
final class Helper
{
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function slug(string $value): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($value)));

        return is_string($slug) ? trim($slug, '-') : '';
    }
}
