<?php
declare(strict_types=1);

namespace Opus\Scaffold;

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;

/** Contract for the canonical profile-aware OPUS application scaffold plan. */
interface SiteScaffoldPlanInterface extends
    OpusFrameworkComponentInterface,
    OpusExceptionAwareInterface,
    OpusProfilerAwareInterface,
    OpusSelfDocumentingInterface
{
    public static function forSite(
        string $siteId,
        string $profile = 'fullstack'
    ): self;

    /** @return list<string> */
    public static function profiles(): array;

    public function profile(): string;
}
