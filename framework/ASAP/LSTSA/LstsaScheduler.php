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

    public function enqueueMemoryBatchSmokeRun(): array
    {
        $definitionXml = <<<'XML'
<lstsa id="p112q2i3_user_email_sync" version="1.0.0">
  <load connection="main" table="raw_users">
    <field name="email" type="string" required="true" min_length="5" max_length="255" max_bytes="512" />
    <field name="status" type="string" required="true" max_length="32" enum="active,inactive,banned" />
  </load>
  <transform>
    <field target="email" source="email" type="email" required="true" max_length="40" max_bytes="120" transform="trim|lower" />
    <field target="is_active" source="status" type="bool" required="true" transform="status_to_bool" />
  </transform>
  <store connection="main" table="users" mode="append" />
  <archive mode="append_only" connection="audit" table="lstsa_runs" path="var/lstsa/archives" />
  <runtime max_run_seconds="120" max_batch_seconds="10" max_rows_per_batch="2" max_memory_mb="128" heartbeat_every_seconds="1" stale_after_seconds="30" />
</lstsa>
XML;

        return $this->enqueue('p112q2i3_user_email_sync', [
            'definition_version' => 'P112Q2I3',
            'mode' => 'memory_batch',
            'definition_xml' => $definitionXml,
            'max_run_seconds' => 120,
            'max_batch_seconds' => 10,
            'max_rows_per_batch' => 2,
            'max_memory_mb' => 128,
            'heartbeat_every_seconds' => 1,
            'stale_after_seconds' => 30,
            'rows' => [
                ['email' => ' Alice@Example.ORG ', 'status' => 'active'],
                ['email' => 'bob@example.org', 'status' => 'inactive'],
                ['email' => 'bad-email', 'status' => 'active'],
                ['email' => 'charlie@example.org', 'status' => 'unknown'],
                ['email' => 'very.long.email.address.that.exceeds.target@example.org', 'status' => 'active'],
            ],
        ], 'p112q2i3_recipe');
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
