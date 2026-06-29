<?php
declare(strict_types=1);

namespace OpusDemo;

/**
 * Package marker for the OPUS Demo OPUS application.
 */
final class DemoPackage
{
    public const PACKAGE = 'logandplay/opus-demo';
    public const SLUG = 'opus-demo';
    public const MANIFEST = 'opus.application.json';

    public static function packageName(): string
    {
        return self::PACKAGE;
    }
}
