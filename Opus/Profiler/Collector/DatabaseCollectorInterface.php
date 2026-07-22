<?php
declare(strict_types=1);

namespace Opus\Profiler\Collector;

interface DatabaseCollectorInterface extends ProfilerCollectorInterface,
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
}