<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;

/** Contract for one OPUS profiler trace. */
interface TraceInterface extends
    OpusFrameworkComponentInterface,
    OpusExceptionAwareInterface,
    OpusProfilerAwareInterface,
    OpusSelfDocumentingInterface
{
    public static function newTraceId(): string;

    public function getTraceId(): string;

    /** @param array<string,mixed> $context */
    public function addEvent(
        string $category,
        string $name,
        array $context = []
    ): void;

    public function finish(): void;

    /** @param array<string,mixed> $summary @return array<string,mixed> */
    public function toArray(array $summary = []): array;
}
