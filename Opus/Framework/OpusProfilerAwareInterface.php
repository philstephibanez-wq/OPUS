<?php
declare(strict_types=1);

namespace Opus\Framework;

/**
 * Marks a class as visible to the OPUS profiler contract.
 *
 * Runtime exceptions and normalized throwables must carry enough context for multi-level profiler traces.
 */
interface OpusProfilerAwareInterface
{
}
