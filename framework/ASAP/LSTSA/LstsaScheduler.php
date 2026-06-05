<?php
declare(strict_types=1);

namespace ASAP\LSTSA;

final class LstsaScheduler
{
    private LstsaRunStore $store;

    public function __construct(LstsaRunStore $store)
    {
        $this->store = $store;
    }

    public function enqueue(string $lstsaId, array $payload = [], string $requestedBy = 'cli'): array
    {
        $payload = $this->withDefaultSchedulingLimits($payload);

        return $this->store->createRun($lstsaId, $payload, $requestedBy);
    }

    public function enqueueSmokeRun(): array
    {
        return $this->enqueue('p112q2i2_smoke_lstsa', [
            'definition_version' => 'P112Q2I2',
            'mode' => 'smoke',
            'max_run_seconds' => 120,
            'max_batch_seconds' => 10,
            'max_rows_per_batch' => 3,
            'max_memory_mb' => 128,
            'heartbeat_every_seconds' => 1,
            'stale_after_seconds' => 30,
            'expected_steps' => [
                'LOAD',
                'SECURE_INPUT',
                'TRANSFORM',
                'SECURE_OUTPUT',
                'STORE',
                'ARCHIVE',
            ],
        ], 'p112q2i2_recipe');
    }

    private function withDefaultSchedulingLimits(array $payload): array
    {
        $payload['max_run_seconds'] = (int)($payload['max_run_seconds'] ?? 300);
        $payload['max_batch_seconds'] = (int)($payload['max_batch_seconds'] ?? 30);
        $payload['max_rows_per_batch'] = (int)($payload['max_rows_per_batch'] ?? 1000);
        $payload['max_memory_mb'] = (int)($payload['max_memory_mb'] ?? 128);
        $payload['heartbeat_every_seconds'] = (int)($payload['heartbeat_every_seconds'] ?? 5);
        $payload['stale_after_seconds'] = (int)($payload['stale_after_seconds'] ?? 60);

        foreach ([
            'max_run_seconds',
            'max_batch_seconds',
            'max_rows_per_batch',
            'max_memory_mb',
            'heartbeat_every_seconds',
            'stale_after_seconds',
        ] as $key) {
            if ($payload[$key] < 1) {
                throw new \InvalidArgumentException('Invalid LSTSA scheduling limit: ' . $key);
            }
        }

        return $payload;
    }
}
