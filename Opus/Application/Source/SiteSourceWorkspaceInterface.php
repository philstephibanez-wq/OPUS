<?php
declare(strict_types=1);

namespace Opus\Application\Source;

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;

/** Contract for bounded, optimistic and atomic OPUS source-file operations. */
interface SiteSourceWorkspaceInterface extends
    OpusFrameworkComponentInterface,
    OpusExceptionAwareInterface,
    OpusProfilerAwareInterface,
    OpusSelfDocumentingInterface
{
    /** @return array<string,mixed> */
    public function list(
        string $siteId,
        int $maxFiles = 5000,
        int $maxBytes = 1048576
    ): array;

    /** @return array<string,mixed> */
    public function read(
        string $siteId,
        string $relativePath,
        int $maxBytes = 1048576
    ): array;

    /** @return array<string,mixed> */
    public function preview(
        string $siteId,
        string $relativePath,
        string $expectedContentHash,
        string $newContent,
        int $maxBytes = 1048576
    ): array;

    /** @return array<string,mixed> */
    public function write(
        string $siteId,
        string $relativePath,
        string $expectedContentHash,
        string $newContent,
        int $maxBytes = 1048576
    ): array;
}
