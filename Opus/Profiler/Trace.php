<?php
declare(strict_types=1);

namespace Opus\Profiler;

/**
 * Causal value object for one OPUS developer-profiler trace.
 *
 * The trace records only observed events. It never infers that HTTP, REST,
 * SCORE, ACL, FSM, Composer or another subsystem ran without a collector event.
 */
final class Trace implements TraceInterface
{
    private const ID_PATTERN = '/^[a-f0-9]{16,64}$/D';
    private const STATUSES = ['success', 'warning', 'error', 'unavailable'];
    private const SENSITIVE_KEYS = [
        'authorization',
        'cookie',
        'password',
        'pass',
        'secret',
        'token',
        'api_key',
        'apikey',
        'access_token',
        'refresh_token',
        'client_secret',
        'private_key',
    ];

    private string $traceId;
    private float $startedAt;
    private ?float $endedAt = null;
    private int $memoryStart;
    /** @var list<array<string,mixed>> */
    private array $events = [];
    /** @var array<string,array<string,mixed>> */
    private array $spans = [];

    public function __construct(?string $traceId = null)
    {
        $traceId = $traceId === null ? self::newTraceId() : strtolower(trim($traceId));
        $this->assertId($traceId, 'OPUS_PROFILER_TRACE_ID_INVALID');
        $this->traceId = $traceId;
        $this->startedAt = microtime(true);
        $this->memoryStart = memory_get_usage(true);
    }

    public static function newTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    /** @param array<string,mixed> $context */
    public function addEvent(
        string $category,
        string $name,
        array $context = [],
        string $status = 'success',
        ?string $spanId = null,
        ?string $parentSpanId = null
    ): string {
        $this->assertOpen();
        $category = $this->normalizeLabel($category, 'OPUS_PROFILER_EVENT_CATEGORY_INVALID');
        $name = $this->normalizeLabel($name, 'OPUS_PROFILER_EVENT_NAME_INVALID');
        $status = $this->normalizeStatus($status);
        $spanId = $spanId ?? self::newSpanId();
        $this->assertId($spanId, 'OPUS_PROFILER_SPAN_ID_INVALID');

        if ($parentSpanId !== null) {
            $this->assertKnownSpan($parentSpanId);
        }

        $now = microtime(true);
        $this->events[] = [
            'event_id' => bin2hex(random_bytes(16)),
            'index' => count($this->events) + 1,
            'observed_at' => $this->formatTime($now),
            'elapsed_ms' => round(($now - $this->startedAt) * 1000, 3),
            'type' => $category . '.' . $name,
            'component' => $category,
            'category' => $category,
            'component' => $category,
            'name' => $name,
            'status' => $status,
            'span_id' => $spanId,
            'parent_span_id' => $parentSpanId,
            'memory' => [
                'usage_bytes' => memory_get_usage(true),
                'peak_bytes' => memory_get_peak_usage(true),
            ],
            'context' => $this->redactValue($context),
        ];

        return $spanId;
    }

    /** @param array<string,mixed> $context */
    public function beginSpan(
        string $category,
        string $name,
        array $context = [],
        ?string $parentSpanId = null
    ): string {
        $this->assertOpen();
        $category = $this->normalizeLabel($category, 'OPUS_PROFILER_SPAN_CATEGORY_INVALID');
        $name = $this->normalizeLabel($name, 'OPUS_PROFILER_SPAN_NAME_INVALID');
        if ($parentSpanId !== null) {
            $this->assertKnownSpan($parentSpanId);
        }

        $spanId = self::newSpanId();
        $now = microtime(true);
        $this->spans[$spanId] = [
            'span_id' => $spanId,
            'parent_span_id' => $parentSpanId,
            'category' => $category,
            'name' => $name,
            'status' => 'running',
            'started_at' => $this->formatTime($now),
            'started_elapsed_ms' => round(($now - $this->startedAt) * 1000, 3),
            'duration_ms' => null,
            'context' => $this->redactValue($context),
        ];
        $this->addEvent(
            $category,
            $name . '.started',
            $context,
            'success',
            $spanId,
            $parentSpanId
        );

        return $spanId;
    }

    /** @param array<string,mixed> $context */
    public function endSpan(
        string $spanId,
        string $status = 'success',
        array $context = []
    ): void {
        $this->assertOpen();
        $this->assertKnownSpan($spanId);
        $status = $this->normalizeStatus($status);
        if (($this->spans[$spanId]['status'] ?? null) !== 'running') {
            throw new \LogicException('OPUS_PROFILER_SPAN_ALREADY_ENDED:' . $spanId);
        }

        $now = microtime(true);
        $startedElapsed = (float) $this->spans[$spanId]['started_elapsed_ms'];
        $this->spans[$spanId]['status'] = $status;
        $this->spans[$spanId]['ended_at'] = $this->formatTime($now);
        $this->spans[$spanId]['duration_ms'] = round(
            (($now - $this->startedAt) * 1000) - $startedElapsed,
            3
        );
        $this->spans[$spanId]['result'] = $this->redactValue($context);

        $this->addEvent(
            (string) $this->spans[$spanId]['category'],
            (string) $this->spans[$spanId]['name'] . '.ended',
            $context,
            $status,
            $spanId,
            is_string($this->spans[$spanId]['parent_span_id'])
                ? $this->spans[$spanId]['parent_span_id']
                : null
        );
    }

    public function finish(): void
    {
        if ($this->endedAt !== null) {
            return;
        }
        foreach ($this->spans as $span) {
            if (($span['status'] ?? null) === 'running') {
                throw new \LogicException(
                    'OPUS_PROFILER_SPAN_NOT_ENDED:' . (string) $span['span_id']
                );
            }
        }
        $this->endedAt = microtime(true);
    }

    /** @param array<string,mixed> $summary @return array<string,mixed> */
    public function toArray(array $summary = []): array
    {
        $endedAt = $this->endedAt ?? microtime(true);
        $statusCounts = [
            'success' => 0,
            'warning' => 0,
            'error' => 0,
            'unavailable' => 0,
        ];
        foreach ($this->events as $event) {
            $status = (string) $event['status'];
            ++$statusCounts[$status];
        }

        return [
            'schema' => 'OPUS_PROFILER_TRACE_V2',
            'trace_id' => $this->traceId,
            'started_at' => $this->formatTime($this->startedAt),
            'duration_ms' => round(($endedAt - $this->startedAt) * 1000, 3),
            'memory' => [
                'start_bytes' => $this->memoryStart,
                'end_bytes' => memory_get_usage(true),
                'peak_bytes' => memory_get_peak_usage(true),
            ],
            'summary' => $this->redactValue($summary),
            'status_counts' => $statusCounts,
            'span_count' => count($this->spans),
            'spans' => array_values($this->spans),
            'event_count' => count($this->events),
            'events' => $this->events,
        ];
    }

    private static function newSpanId(): string
    {
        return bin2hex(random_bytes(8));
    }

    private function assertOpen(): void
    {
        if ($this->endedAt !== null) {
            throw new \LogicException('OPUS_PROFILER_TRACE_ALREADY_FINISHED');
        }
    }

    private function assertId(string $id, string $error): void
    {
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            throw new \InvalidArgumentException($error);
        }
    }

    private function assertKnownSpan(string $spanId): void
    {
        $this->assertId($spanId, 'OPUS_PROFILER_SPAN_ID_INVALID');
        if (!isset($this->spans[$spanId])) {
            throw new \InvalidArgumentException(
                'OPUS_PROFILER_PARENT_SPAN_UNKNOWN:' . $spanId
            );
        }
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('OPUS_PROFILER_STATUS_INVALID');
        }

        return $status;
    }

    private function normalizeLabel(string $label, string $error): string
    {
        $label = strtolower(trim($label));
        if (preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $label) !== 1) {
            throw new \InvalidArgumentException($error);
        }

        return $label;
    }

    private function formatTime(float $timestamp): string
    {
        $seconds = (int) $timestamp;
        $microseconds = (int) round(($timestamp - $seconds) * 1000000);

        return gmdate('Y-m-d\TH:i:s', $seconds)
            . sprintf('.%06dZ', $microseconds);
    }

    /** @return mixed */
    private function redactValue(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
            return '[REDACTED]';
        }
        if (!is_array($value)) {
            return $value;
        }

        $redacted = [];
        foreach ($value as $childKey => $childValue) {
            $redacted[$childKey] = $this->redactValue(
                $childValue,
                is_string($childKey) ? $childKey : null
            );
        }

        return $redacted;
    }
}
