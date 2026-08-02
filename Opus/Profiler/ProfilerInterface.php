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
        array $context = [],
        string $status = 'success',
        ?string $spanId = null,
        ?string $parentSpanId = null
    ): string;

    /** @param array<string,mixed> $context */
    public function beginSpan(
        string $category,
        string $name,
        array $context = [],
        ?string $parentSpanId = null
    ): string;

    /** @param array<string,mixed> $context */
    public function endSpan(
        string $spanId,
        string $status = 'success',
        array $context = []
    ): void;

    /** @param array<string,mixed> $summary */
    public function stop(array $summary = []): string;

    /** @param array<string,mixed> $summary */
    public function writeTrace(Trace $trace, array $summary = []): string;

    /**
     * Reads both OPUS_PROFILER_TRACE_V1 and OPUS_PROFILER_TRACE_V2 records.
     *
     * @return list<array<string,mixed>>
     */
    public function readTrace(string $traceId): array;

    /** @param list<array<string,mixed>> $records */
    public function importRecords(array $records, ?string $rootParentSpanId = null): void;
}
