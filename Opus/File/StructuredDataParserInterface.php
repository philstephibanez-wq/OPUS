<?php
declare(strict_types=1);

namespace Opus\File;

interface StructuredDataParserInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /** @return array<mixed> */
    public function parse(string $contents, string $source = ''): array;

    /** @return list<string> */
    public function extensions(): array;
}
