<?php
declare(strict_types=1);

namespace Opus\File;

/**
 * Contract interface for Opus\File\Yaml.
 *
 * @generated-by P117N_OPUS_FILE_I18N_LOCALE
 */
interface YamlInterface extends
    StructuredDataParserInterface,
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public static function instance(): self;

    /** @return array<mixed> */
    public function parse(string $contents, string $source = ''): array;

    /** @return list<string> */
    public function extensions(): array;
}
