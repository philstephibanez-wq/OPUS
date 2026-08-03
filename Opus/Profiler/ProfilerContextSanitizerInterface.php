<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;

/** Contract for bounded, recursively redacted developer-profiler values. */
interface ProfilerContextSanitizerInterface extends
    OpusFrameworkComponentInterface,
    OpusExceptionAwareInterface,
    OpusProfilerAwareInterface,
    OpusSelfDocumentingInterface
{
    /** @return mixed */
    public function sanitize(mixed $value): mixed;
}
