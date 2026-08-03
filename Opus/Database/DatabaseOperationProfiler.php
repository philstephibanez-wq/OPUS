<?php
declare(strict_types=1);

namespace Opus\Database;

use Opus\Profiler\ProfilerInterface;

/** Measures database operations with bounded, centrally sanitized diagnostics. */
final class DatabaseOperationProfiler implements DatabaseOperationProfilerInterface
{
    public function __construct(
        private readonly ProfilerInterface $profiler,
        private readonly ?string $parentSpanId = null
    ) {
    }

    public function measure(
        string $driver,
        string $operationName,
        callable $operation,
        array $context = []
    ): mixed {
        $driver = strtolower(trim($driver));
        $operationName = strtolower(trim($operationName));
        if ($driver === '' || $operationName === '') {
            throw new \InvalidArgumentException(
                'OPUS_DATABASE_PROFILER_OPERATION_INVALID'
            );
        }

        $trace = $this->profiler->getActiveTrace();
        if ($trace === null) {
            return $operation();
        }

        $safeContext = $this->safeContext($context);
        $safeContext['driver'] = $driver;
        $safeContext['operation'] = $operationName;
        $startedAt = microtime(true);
        $spanId = $this->profiler->beginSpan(
            'database',
            'database.operation',
            $safeContext,
            $this->parentSpanId
        );
        $this->profiler->event(
            'database',
            'database.operation.started',
            $safeContext,
            'success',
            $spanId,
            $this->parentSpanId
        );

        try {
            $result = $operation();
            $resultContext = $safeContext;
            if (is_array($result) || is_scalar($result) || $result === null) {
                $resultContext['result'] = $result;
            } elseif (is_object($result)) {
                $resultContext['result_type'] = $result::class;
            }
            $this->profiler->event(
                'database',
                $this->successEventName($operationName),
                $resultContext + [
                    'duration_ms' => $this->durationMs($startedAt),
                ],
                'success',
                $spanId,
                $this->parentSpanId
            );
            $this->profiler->endSpan($spanId, 'success', [
                'duration_ms' => $this->durationMs($startedAt),
            ]);
            return $result;
        } catch (\Throwable $error) {
            $failure = $safeContext + [
                'duration_ms' => $this->durationMs($startedAt),
                'exception_class' => $error::class,
            ];
            $this->profiler->event(
                'database',
                'database.operation.failed',
                $failure,
                'error',
                $spanId,
                $this->parentSpanId
            );
            $this->profiler->endSpan($spanId, 'error', $failure);
            throw $error;
        }
    }

    public function result(
        string $driver,
        string $operationName,
        mixed $result,
        array $context = []
    ): void {
        if ($this->profiler->getActiveTrace() === null) {
            return;
        }
        $this->profiler->event('database', 'database.result.observed', [
            'driver' => strtolower(trim($driver)),
            'operation' => strtolower(trim($operationName)),
            'result' => $result,
        ] + $this->safeContext($context));
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function safeContext(array $context): array
    {
        $allowed = [
            'database',
            'resource',
            'statement_type',
            'transaction',
            'origin',
            'sql',
            'parameters',
            'result',
            'affected_rows',
            'returned_rows',
            'columns',
            'result_type',
        ];
        return array_intersect_key($context, array_fill_keys($allowed, true));
    }

    private function durationMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 3);
    }

    private function successEventName(string $operationName): string
    {
        return match ($operationName) {
            'connection.open' => 'database.connection.opened',
            'transaction.begin' => 'database.transaction.started',
            'transaction.commit' => 'database.transaction.committed',
            'transaction.rollback' => 'database.transaction.rolled_back',
            default => 'database.operation.completed',
        };
    }
}
