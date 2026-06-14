<?php

declare(strict_types=1);

namespace Opus\Helper;

/*
 * OPUS_REFBOOK:
 *   domain: HELPER
 *   role: Class Helper belongs to the HELPER Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the HELPER domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - helper-overview
 *   diagrams:
 *     - helper-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC HELPER
 *
 * Role:
 *   Mutualize deterministic presentation-safe helper functions.
 *
 * Responsibility:
 *   Provide small reusable transformations shared by controllers, services,
 *   templates and documentation tooling when no business decision is involved.
 *
 * Contract:
 *   Helpers transform only. They do not load data and do not render complete pages.
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
