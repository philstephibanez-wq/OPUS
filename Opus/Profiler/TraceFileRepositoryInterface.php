<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Framework\OpusFrameworkComponentInterface;

interface TraceFileRepositoryInterface extends OpusFrameworkComponentInterface
{
    public function listTraces(): array;
    public function readTrace(string $traceId): array;
}