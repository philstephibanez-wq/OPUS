<?php
declare(strict_types=1);

namespace Opus\Application\Runtime;

interface GeneratedSiteRuntimeInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function handle(): \Opus\Http\Response;
}
