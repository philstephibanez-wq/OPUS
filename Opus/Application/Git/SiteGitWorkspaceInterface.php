<?php
declare(strict_types=1);

namespace Opus\Application\Git;

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;

/** Contract for bounded, repository-scoped Git operations on an OPUS site. */
interface SiteGitWorkspaceInterface extends
    OpusFrameworkComponentInterface,
    OpusExceptionAwareInterface,
    OpusProfilerAwareInterface,
    OpusSelfDocumentingInterface
{
    /** @return array<string,mixed> */
    public function status(string $siteId): array;

    /** @return array<string,mixed> */
    public function diff(string $siteId, string $relativePath): array;

    /** @return array<string,mixed> */
    public function history(string $siteId, int $limit = 20): array;

    /** @return array<string,mixed> */
    public function stage(string $siteId, string $relativePath): array;

    /** @return array<string,mixed> */
    public function stageAll(string $siteId): array;

    /** @return array<string,mixed> */
    public function unstage(string $siteId, string $relativePath): array;

    /** @return array<string,mixed> */
    public function commit(string $siteId, string $message): array;

    /** @return array<string,mixed> */
    public function restore(
        string $siteId,
        string $relativePath,
        string $expectedContentHash,
        string $confirmation
    ): array;
}
