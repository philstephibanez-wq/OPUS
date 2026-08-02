<?php
declare(strict_types=1);

namespace Opus\Database;

use Opus\Profiler\ProfilerInterface;

/** Measures database operations without retaining SQL text or parameters. */
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
            $this->profiler->event(
                'database',
                $this->successEventName($operationName),
                $safeContext + [
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

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function safeContext(array $context): array
    {
        $allowed = [
            'database',
            'resource',
            'statement_type',
            'transaction',
            'origin',
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
