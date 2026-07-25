<?php
declare(strict_types=1);

namespace Opus\Scaffold;

interface LayeredSiteScaffoldPlanInterface extends
    ScaffoldPlanInterface,
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function profile(): string;

    /** @return list<string> */
    public static function profiles(): array;
}
