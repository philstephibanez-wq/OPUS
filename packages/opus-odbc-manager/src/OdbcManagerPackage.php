<?php
declare(strict_types=1);

namespace OpusOdbcManager;

/**
 * Package marker for the Composer-installable OPUS ODBC Manager application.
 */
final class OdbcManagerPackage
{
    public const PACKAGE = 'logandplay/opus-odbc-manager';
    public const SLUG = 'opus-odbc-manager';
    public const MANIFEST = 'opus.application.json';
    public const ROUTES = 'app/routes.php';
    public const ACL = 'config/acl.php';
    public const NAVIGATION = 'config/navigation.php';
    public const MODE = 'readonly';

    public static function packageName(): string
    {
        return self::PACKAGE;
    }

    public static function slug(): string
    {
        return self::SLUG;
    }

    public static function isReadOnly(): bool
    {
        return self::MODE === 'readonly';
    }
}
