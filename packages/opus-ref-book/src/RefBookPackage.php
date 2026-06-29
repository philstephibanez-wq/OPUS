<?php
declare(strict_types=1);

namespace OpusRefBook;

/**
 * Package marker for the OPUS RefBook OPUS application.
 */
final class RefBookPackage
{
    public const PACKAGE = 'logandplay/opus-ref-book';
    public const SLUG = 'opus-ref-book';
    public const MANIFEST = 'opus.application.json';

    public static function packageName(): string
    {
        return self::PACKAGE;
    }
}
