<?php
declare(strict_types=1);

namespace Opus\Application\Inspection;

interface SiteSourceInspectorInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /** @return array<string,mixed> */
    public function list(string $siteId, int $maxFiles = 5000, int $maxBytes = 1048576): array;

    /** @return array<string,mixed> */
    public function read(string $siteId, string $relativePath, int $maxBytes = 1048576): array;
}
