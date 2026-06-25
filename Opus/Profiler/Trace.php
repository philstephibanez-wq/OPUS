<?php
declare(strict_types=1);

namespace Opus\Profiler;

/**
 * OPUS profiler trace value object.
 *
 * A trace records dev-only diagnostic events for one execution flow.
 * It is intentionally independent from legacy legacy debug.
 */
final class Trace
{
    private string $traceId;
    private float $startedAt;
    private ?float $endedAt = null;
    private int $memoryStart;
    /** @var array<int,array<string,mixed>> */
    private array $events = [];

    public function __construct(?string $traceId = null)
    {
        $this->traceId = $traceId ?? self::newTraceId();
        $this->startedAt = microtime(true);
        $this->memoryStart = memory_get_usage(true);
    }

    public static function newTraceId(): string
    {
        return bin2hex(random_bytes(8));
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    /** @param array<string,mixed> $context */
    public function addEvent(string $category, string $name, array $context = []): void
    {
        $now = microtime(true);

        $this->events[] = [
            'index' => count($this->events) + 1,
            'time' => gmdate('c'),
            'elapsed_ms' => round(($now - $this->startedAt) * 1000, 3),
            'category' => $category,
            'name' => $name,
            'memory' => [
                'usage_bytes' => memory_get_usage(true),
                'peak_bytes' => memory_get_peak_usage(true),
            ],
            'context' => $this->redactContext($context),
        ];
    }

    public function finish(): void
    {
        if ($this->endedAt === null) {
            $this->endedAt = microtime(true);
        }
    }

    /** @param array<string,mixed> $summary */
    public function toArray(array $summary = []): array
    {
        $endedAt = $this->endedAt ?? microtime(true);

        return [
            'schema' => 'OPUS_PROFILER_TRACE_V1',
            'trace_id' => $this->traceId,
            'started_at' => gmdate('c', (int) $this->startedAt),
            'duration_ms' => round(($endedAt - $this->startedAt) * 1000, 3),
            'memory' => [
                'start_bytes' => $this->memoryStart,
                'end_bytes' => memory_get_usage(true),
                'peak_bytes' => memory_get_peak_usage(true),
            ],
            'summary' => $this->redactContext($summary),
            'event_count' => count($this->events),
            'events' => $this->events,
        ];
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function redactContext(array $context): array
    {
        $redacted = [];

        foreach ($context as $key => $value) {
            $normalized = strtolower((string) $key);

            if (in_array($normalized, ['password', 'pass', 'secret', 'token', 'api_key', 'apikey', 'authorization'], true)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }
}
