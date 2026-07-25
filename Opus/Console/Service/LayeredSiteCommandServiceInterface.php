<?php
declare(strict_types=1);

namespace Opus\Console\Service;

interface LayeredSiteCommandServiceInterface extends
    SiteCommandServiceInterface,
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
}
