<?php
declare(strict_types=1);

namespace Opus\Scaffold;

/**
 * Single scaffold entry canonicalized before preview and filesystem writing.
 */
final class ScaffoldEntry implements ScaffoldEntryInterface
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
        $relativePath = ProfilerEnvironmentScaffoldPolicy::instance()
            ->normalizeDirectory($relativePath);
        return new self(self::TYPE_DIRECTORY, $relativePath);
    }

    public static function file(string $relativePath, string $content): self
    {
        [$relativePath, $content] = ProfilerEnvironmentScaffoldPolicy::instance()
            ->normalizeFile($relativePath, $content);
        return new self(self::TYPE_FILE, $relativePath, $content);
    }
}
