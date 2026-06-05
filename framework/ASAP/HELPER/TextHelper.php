<?php

declare(strict_types=1);

namespace ASAP\Helper;

/**
 * PUBLIC HELPER
 *
 * Role:
 *   Provide deterministic text helpers.
 *
 * Contract:
 *   Helper is pure transformation only.
 *
 * Since:
 *   P112D4B
 */
final class TextHelper
{
    public static function slug(string $text): string
    {
        $normalized = strtolower(trim($text));
        $normalized = preg_replace('/[^a-z0-9]+/i', '-', $normalized);

        if (!is_string($normalized)) {
            return '';
        }

        return trim($normalized, '-');
    }
}
