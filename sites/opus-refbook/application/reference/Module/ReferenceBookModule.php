<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Module;

/**
 * PUBLIC MODULE
 *
 * Role:
 *   Identify the Opus Reference Book application module.
 *
 * Contract:
 *   Metadata only. No routing, no rendering, no filesystem access.
 */
final class ReferenceBookModule
{
    public function id(): string
    {
        return 'opus-reference-book';
    }

    public function title(): string
    {
        return 'Opus Reference Book';
    }
}
