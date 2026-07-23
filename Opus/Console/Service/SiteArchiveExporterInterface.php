<?php
declare(strict_types=1);

namespace Opus\Console\Service;

interface SiteArchiveExporterInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /** @return array<string,mixed> */
    public function export(string $siteId, string $outputRelative, bool $overwrite): array;
}
