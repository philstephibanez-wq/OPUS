<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;

/** Contract for the OPUS execution profiler. */
interface ProfilerInterface extends
    OpusFrameworkComponentInterface,
    OpusExceptionAwareInterface,
    OpusProfilerAwareInterface,
    OpusSelfDocumentingInterface
{
    public function start(?string $traceId = null): TraceInterface;

    public function getActiveTrace(): ?TraceInterface;

    /** @param array<string,mixed> $context */
    public function event(
        string $category,
        string $name,
        array $context = []
    ): void;

    /** @param array<string,mixed> $summary */
    public function stop(array $summary = []): string;

    /** @param array<string,mixed> $summary */
    public function writeTrace(Trace $trace, array $summary = []): string;
}
