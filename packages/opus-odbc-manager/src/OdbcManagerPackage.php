<?php
declare(strict_types=1);

namespace OpusOdbcManager;

/**
 * Package marker for the OPUS ODBC Manager OPUS application.
 */
final class OdbcManagerPackage
{
    public const PACKAGE = 'logandplay/opus-odbc-manager';
    public const SLUG = 'opus-odbc-manager';
    public const MANIFEST = 'opus.application.json';

    public static function packageName(): string
    {
        return self::PACKAGE;
    }
}
