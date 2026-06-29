<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * Canonical OPUS LSTSAR stage names.
 *
 * LSTSAR means Load, Securize, Transform, Store, Archive and Report.
 */
final class LstsarStageName
{
    public const LOAD = 'load';
    public const SECURIZE = 'securize';
    public const TRANSFORM = 'transform';
    public const STORE = 'store';
    public const ARCHIVE = 'archive';
    public const REPORT = 'report';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::LOAD,
            self::SECURIZE,
            self::TRANSFORM,
            self::STORE,
            self::ARCHIVE,
            self::REPORT,
        ];
    }

    public static function normalize(string $stage): string
    {
        $stage = strtolower(trim($stage));
        if ($stage === 'secure') {
            return self::SECURIZE;
        }
        if (!in_array($stage, self::all(), true)) {
            throw new \InvalidArgumentException('OPUS_LSTSAR_STAGE_UNSUPPORTED: ' . $stage);
        }

        return $stage;
    }
}
