<?php
declare(strict_types=1);

namespace Opus\Scaffold;

/**
 * Single scaffold entry.
 */
final class ScaffoldEntry
{
    public const TYPE_DIRECTORY = 'directory';
    public const TYPE_FILE = 'file';

    public function __construct(
        public readonly string $type,
        public readonly string $relativePath,
        public readonly ?string $content = null
    ) {
    }

    public static function directory(string $relativePath): self
    {
        return new self(self::TYPE_DIRECTORY, $relativePath);
    }

    public static function file(string $relativePath, string $content): self
    {
        return new self(self::TYPE_FILE, $relativePath, $content);
    }
}
