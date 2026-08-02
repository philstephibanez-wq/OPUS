<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;

/** Contract for one causal OPUS profiler trace. */
interface TraceInterface extends
    OpusFrameworkComponentInterface,
    OpusExceptionAwareInterface,
    OpusProfilerAwareInterface,
    OpusSelfDocumentingInterface
{
    public static function newTraceId(): string;

    public function getTraceId(): string;

    /**
     * Records one observed event.
     *
     * @param array<string,mixed> $context
     */
    public function addEvent(
        string $category,
        string $name,
        array $context = [],
        string $status = 'success',
        ?string $spanId = null,
        ?string $parentSpanId = null
    ): string;

    /**
     * Starts one causal span and returns its identifier.
     *
     * @param array<string,mixed> $context
     */
    public function beginSpan(
        string $category,
        string $name,
        array $context = [],
        ?string $parentSpanId = null
    ): string;

    /**
     * Ends an existing span.
     *
     * @param array<string,mixed> $context
     */
    public function endSpan(
        string $spanId,
        string $status = 'success',
        array $context = []
    ): void;

    public function finish(): void;

    /**
     * Imports one measured record from another component of the same trace.
     *
     * @param array<string,mixed> $record
     */
    public function importRecord(array $record, ?string $rootParentSpanId = null): void;

    /** @param array<string,mixed> $summary @return array<string,mixed> */
    public function toArray(array $summary = []): array;
}
