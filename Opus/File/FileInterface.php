<?php
declare(strict_types=1);

namespace Opus\File;

/**
 * Contract interface for Opus\File\File.
 *
 * @generated-by P117N_OPUS_FILE_I18N_LOCALE
 */
interface FileInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public static function instance(): self;

    public function exists(string $path): bool;

    public function read(string $path, ?int $maxBytes = null): string;

    public function writeAtomic(string $path, string $contents): void;

    public function delete(string $path): void;

    /** @return list<string> */
    public function matching(string $pattern): array;

    public function extension(string $path): string;
}
