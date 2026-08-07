<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;

/** Contract for environment-driven OPUS Profiler registration policy. */
interface ProfilerConfigurationInterface extends
    OpusFrameworkComponentInterface,
    OpusExceptionAwareInterface,
    OpusProfilerAwareInterface,
    OpusSelfDocumentingInterface
{
    public function environment(): string;

    public function collectEnabled(): bool;

    public function webEnabled(): bool;

    public function linksEnabled(): bool;
}
