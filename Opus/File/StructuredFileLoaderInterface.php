<?php
declare(strict_types=1);

namespace Opus\File;

/**
 * Contract interface for Opus\File\StructuredFileLoader.
 *
 * @generated-by P117N_OPUS_FILE_I18N_LOCALE
 */
interface StructuredFileLoaderInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public static function instance(): self;

    /** @return array<mixed> */
    public function read(string $path, ?int $maxBytes = null): array;

    /** @param array<mixed> $data */
    public function writeJson(string $path, array $data, bool $pretty = true): void;

    /** @return list<string> */
    public function supportedExtensions(): array;
}
