<?php
declare(strict_types=1);

namespace ASAP\LSTSA;

final class LstsaRunStore
{
    private string $projectRoot;
    private string $lstsaRoot;

    public function __construct(string $projectRoot)
    {
        $root = rtrim($projectRoot, DIRECTORY_SEPARATOR);
        if ($root === '' || !is_dir($root)) {
            throw new \RuntimeException('Invalid ASAP project root: ' . $projectRoot);
        }

        $this->projectRoot = $root;
        $this->lstsaRoot = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'lstsa';
        $this->ensureRuntimeDirectories();
    }

    public function createRun(string $lstsaId, array $payload = [], string $requestedBy = 'cli'): array
    {
        $this->assertCleanId($lstsaId, 'lstsa_id');

        $runId = $this->buildRunId($lstsaId);
        $now = $this->now();

        $run = [
            'run_id' => $runId,
            'lstsa_id' => $lstsaId,
            'status' => LstsaRunStatus::PENDING,
            'requested_by' => $requestedBy,
            'created_at' => $now,
            'updated_at' => $now,
            'started_at' => null,
            'finished_at' => null,
            'runner_id' => null,
            'current_step' => 'PENDING',
            'limits' => [
                'max_run_seconds' => (int)($payload['max_run_seconds'] ?? 300),
                'max_batch_seconds' => (int)($payload['max_batch_seconds'] ?? 30),
                'max_rows_per_batch' => (int)($payload['max_rows_per_batch'] ?? 1000),
                'max_memory_mb' => (int)($payload['max_memory_mb'] ?? 128),
                'heartbeat_every_seconds' => (int)($payload['heartbeat_every_seconds'] ?? 5),
                'stale_after_seconds' => (int)($payload['stale_after_seconds'] ?? 60),
            ],
            'counts' => [
                'loaded' => 0,
                'accepted' => 0,
                'transformed' => 0,
                'stored' => 0,
                'archived' => 0,
                'checkpoints' => 0,
                'rejected' => 0,
                'errors' => 0,
            ],
            'payload' => $payload,
            'artifacts' => [],
            'report_json' => null,
            'report_md' => null,
            'error' => null,
        ];

        $this->writeRun($run);

        return $run;
    }

    public function acquirePendingRun(string $runnerId): ?array
    {
        $this->assertCleanId($runnerId, 'runner_id');

        $runs = $this->listRunsByStatus(LstsaRunStatus::PENDING);
        usort($runs, static fn(array $a, array $b): int => strcmp((string)$a['created_at'], (string)$b['created_at']));

        foreach ($runs as $run) {
            $lockPath = $this->lockPath((string)$run['run_id']);
            $lockHandle = @fopen($lockPath, 'x');

            if ($lockHandle === false) {
                continue;
            }

            fwrite($lockHandle, json_encode([
                'run_id' => $run['run_id'],
                'runner_id' => $runnerId,
                'locked_at' => $this->now(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            fclose($lockHandle);

            $now = $this->now();
            $run['status'] = LstsaRunStatus::RUNNING;
            $run['runner_id'] = $runnerId;
            $run['started_at'] = $now;
            $run['updated_at'] = $now;
            $run['current_step'] = 'ACQUIRED';
            $run['artifacts'] = is_array($run['artifacts'] ?? null) ? $run['artifacts'] : [];

            $this->writeRun($run);
            $this->heartbeat($run, 'ACQUIRED');

            return $run;
        }

        return null;
    }

    public function heartbeat(array &$run, string $step, array $counts = []): void
    {
        $run['current_step'] = $step;
        $run['updated_at'] = $this->now();

        foreach ($counts as $name => $value) {
            if (array_key_exists($name, $run['counts'])) {
                $run['counts'][$name] = (int)$value;
            }
        }

        $heartbeat = [
            'run_id' => $run['run_id'],
            'lstsa_id' => $run['lstsa_id'],
            'status' => $run['status'],
            'runner_id' => $run['runner_id'],
            'current_step' => $run['current_step'],
            'counts' => $run['counts'],
            'last_heartbeat_at' => $run['updated_at'],
        ];

        $this->writeJson($this->heartbeatPath((string)$run['run_id']), $heartbeat);
        $this->writeRun($run);
    }

    public function writeCheckpoint(array &$run, int $batchIndex, array $payload): string
    {
        if ($batchIndex < 1) {
            throw new \InvalidArgumentException('Invalid LSTSA checkpoint batch index');
        }

        $path = $this->artifactPath($run, 'checkpoints', 'batch_' . str_pad((string)$batchIndex, 4, '0', STR_PAD_LEFT) . '.json');
        if (file_exists($path)) {
            throw new \RuntimeException('LSTSA checkpoint append-only violation: ' . $path);
        }

        $payload['run_id'] = $run['run_id'];
        $payload['lstsa_id'] = $run['lstsa_id'];
        $payload['batch_index'] = $batchIndex;
        $payload['created_at'] = $this->now();

        $this->writeJson($path, $payload);
        $this->registerArtifact($run, 'checkpoints', $path);
        $run['counts']['checkpoints'] = count($run['artifacts']['checkpoints']);
        $this->writeRun($run);

        return $path;
    }

    public function writeArchivePayload(array &$run, string $suffix, array $payload): string
    {
        $path = $this->artifactPath($run, 'archives', $suffix);
        if (file_exists($path)) {
            throw new \RuntimeException('LSTSA archive append-only violation: ' . $path);
        }

        $this->writeJson($path, [
            'run_id' => $run['run_id'],
            'lstsa_id' => $run['lstsa_id'],
            'created_at' => $this->now(),
            'payload' => $payload,
        ]);
        $this->registerArtifact($run, 'archives', $path);
        $run['counts']['archived'] = count($run['artifacts']['archives']);
        $this->writeRun($run);

        return $path;
    }

    public function writeQuarantinePayload(array &$run, string $suffix, array $payload): string
    {
        $path = $this->artifactPath($run, 'quarantine', $suffix);
        if (file_exists($path)) {
            throw new \RuntimeException('LSTSA quarantine append-only violation: ' . $path);
        }

        $this->writeJson($path, [
            'run_id' => $run['run_id'],
            'lstsa_id' => $run['lstsa_id'],
            'created_at' => $this->now(),
            'payload' => $payload,
        ]);
        $this->registerArtifact($run, 'quarantine', $path);
        $this->writeRun($run);

        return $path;
    }

    public function finish(array &$run, string $status, array $summary = []): array
    {
        LstsaRunStatus::assertValid($status);

        $run['status'] = $status;
        $run['finished_at'] = $this->now();
        $run['updated_at'] = $run['finished_at'];
        $run['current_step'] = $status;

        foreach (($summary['counts'] ?? []) as $name => $value) {
            if (array_key_exists($name, $run['counts'])) {
                $run['counts'][$name] = (int)$value;
            }
        }

        if (isset($summary['artifacts']) && is_array($summary['artifacts'])) {
            foreach ($summary['artifacts'] as $kind => $paths) {
                foreach ((array)$paths as $path) {
                    $this->registerArtifact($run, (string)$kind, (string)$path);
                }
            }
        }

        if (isset($summary['error'])) {
            $run['error'] = (string)$summary['error'];
        }

        $report = $this->writeReport($run);
        $run['report_json'] = $report['json'];
        $run['report_md'] = $report['md'];

        $this->writeRun($run);
        $this->releaseLock((string)$run['run_id']);

        return $run;
    }

    public function listRunsByStatus(?string $status = null): array
    {
        if ($status !== null) {
            LstsaRunStatus::assertValid($status);
        }

        $runs = [];
        foreach (glob($this->queueDir() . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
            $run = $this->readJson($path);
            if (!is_array($run) || !isset($run['run_id'])) {
                continue;
            }
            if ($status !== null && ($run['status'] ?? null) !== $status) {
                continue;
            }
            $runs[] = $run;
        }

        return $runs;
    }

    public function readRun(string $runId): array
    {
        $this->assertCleanId($runId, 'run_id');
        $path = $this->runPath($runId);

        if (!is_file($path)) {
            throw new \RuntimeException('LSTSA run not found: ' . $runId);
        }

        $run = $this->readJson($path);
        if (!is_array($run)) {
            throw new \RuntimeException('Invalid LSTSA run JSON: ' . $runId);
        }

        return $run;
    }

    public function writeRun(array $run): void
    {
        if (!isset($run['run_id'])) {
            throw new \InvalidArgumentException('Missing run_id');
        }
        if (!isset($run['status'])) {
            throw new \InvalidArgumentException('Missing status');
        }

        $this->assertCleanId((string)$run['run_id'], 'run_id');
        LstsaRunStatus::assertValid((string)$run['status']);
        $run['artifacts'] = is_array($run['artifacts'] ?? null) ? $run['artifacts'] : [];

        $this->writeJson($this->runPath((string)$run['run_id']), $run);
    }

    public function writeReport(array $run): array
    {
        $runId = (string)$run['run_id'];
        $this->assertCleanId($runId, 'run_id');

        $safeLstsaId = $this->safeSegment((string)$run['lstsa_id']);
        $dir = $this->reportsDir() . DIRECTORY_SEPARATOR . $safeLstsaId;

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create LSTSA report directory: ' . $dir);
        }

        $jsonPath = $dir . DIRECTORY_SEPARATOR . $runId . '.json';
        $mdPath = $dir . DIRECTORY_SEPARATOR . $runId . '.md';

        $report = [
            'run_id' => $run['run_id'],
            'lstsa_id' => $run['lstsa_id'],
            'status' => $run['status'],
            'requested_by' => $run['requested_by'] ?? null,
            'runner_id' => $run['runner_id'] ?? null,
            'created_at' => $run['created_at'] ?? null,
            'started_at' => $run['started_at'] ?? null,
            'finished_at' => $run['finished_at'] ?? null,
            'current_step' => $run['current_step'] ?? null,
            'limits' => $run['limits'] ?? [],
            'counts' => $run['counts'] ?? [],
            'payload' => $run['payload'] ?? [],
            'artifacts' => $run['artifacts'] ?? [],
            'error' => $run['error'] ?? null,
        ];

        $this->writeJson($jsonPath, $report);

        $md = [];
        $md[] = '# LSTSA run report';
        $md[] = '';
        $md[] = '- run_id: `' . $run['run_id'] . '`';
        $md[] = '- lstsa_id: `' . $run['lstsa_id'] . '`';
        $md[] = '- status: `' . $run['status'] . '`';
        $md[] = '- current_step: `' . ($run['current_step'] ?? '') . '`';
        $md[] = '- created_at: `' . ($run['created_at'] ?? '') . '`';
        $md[] = '- started_at: `' . ($run['started_at'] ?? '') . '`';
        $md[] = '- finished_at: `' . ($run['finished_at'] ?? '') . '`';
        $md[] = '';
        $md[] = '## Counts';
        $md[] = '';
        foreach (($run['counts'] ?? []) as $name => $value) {
            $md[] = '- ' . $name . ': `' . $value . '`';
        }
        $md[] = '';
        $md[] = '## Artifacts';
        $md[] = '';
        foreach (($run['artifacts'] ?? []) as $kind => $paths) {
            foreach ((array)$paths as $path) {
                $md[] = '- ' . $kind . ': `' . $path . '`';
            }
        }
        $md[] = '';

        file_put_contents($mdPath, implode(PHP_EOL, $md) . PHP_EOL, LOCK_EX);

        return [
            'json' => $jsonPath,
            'md' => $mdPath,
        ];
    }

    public function projectRoot(): string
    {
        return $this->projectRoot;
    }

    private function ensureRuntimeDirectories(): void
    {
        foreach ([
            $this->lstsaRoot,
            $this->queueDir(),
            $this->locksDir(),
            $this->heartbeatsDir(),
            $this->reportsDir(),
            $this->archivesDir(),
            $this->quarantineDir(),
            $this->checkpointsDir(),
        ] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('Cannot create LSTSA runtime directory: ' . $dir);
            }
        }
    }

    private function queueDir(): string
    {
        return $this->lstsaRoot . DIRECTORY_SEPARATOR . 'queue';
    }

    private function locksDir(): string
    {
        return $this->lstsaRoot . DIRECTORY_SEPARATOR . 'locks';
    }

    private function heartbeatsDir(): string
    {
        return $this->lstsaRoot . DIRECTORY_SEPARATOR . 'heartbeats';
    }

    private function reportsDir(): string
    {
        return $this->lstsaRoot . DIRECTORY_SEPARATOR . 'reports';
    }

    private function archivesDir(): string
    {
        return $this->lstsaRoot . DIRECTORY_SEPARATOR . 'archives';
    }

    private function quarantineDir(): string
    {
        return $this->lstsaRoot . DIRECTORY_SEPARATOR . 'quarantine';
    }

    private function checkpointsDir(): string
    {
        return $this->lstsaRoot . DIRECTORY_SEPARATOR . 'checkpoints';
    }

    private function runPath(string $runId): string
    {
        return $this->queueDir() . DIRECTORY_SEPARATOR . $runId . '.json';
    }

    private function lockPath(string $runId): string
    {
        return $this->locksDir() . DIRECTORY_SEPARATOR . $runId . '.lock';
    }

    private function heartbeatPath(string $runId): string
    {
        return $this->heartbeatsDir() . DIRECTORY_SEPARATOR . $runId . '.json';
    }

    private function artifactPath(array $run, string $kind, string $suffix): string
    {
        $this->assertCleanId((string)$run['run_id'], 'run_id');
        $safeLstsaId = $this->safeSegment((string)$run['lstsa_id']);
        $safeSuffix = $this->safeSuffix($suffix);

        $baseDir = match ($kind) {
            'archives' => $this->archivesDir(),
            'quarantine' => $this->quarantineDir(),
            'checkpoints' => $this->checkpointsDir(),
            default => throw new \InvalidArgumentException('Unknown LSTSA artifact kind: ' . $kind),
        };

        $dir = $baseDir . DIRECTORY_SEPARATOR . $safeLstsaId;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create LSTSA artifact directory: ' . $dir);
        }

        return $dir . DIRECTORY_SEPARATOR . (string)$run['run_id'] . '_' . $safeSuffix;
    }

    private function registerArtifact(array &$run, string $kind, string $path): void
    {
        if (!isset($run['artifacts']) || !is_array($run['artifacts'])) {
            $run['artifacts'] = [];
        }
        if (!isset($run['artifacts'][$kind]) || !is_array($run['artifacts'][$kind])) {
            $run['artifacts'][$kind] = [];
        }
        if (!in_array($path, $run['artifacts'][$kind], true)) {
            $run['artifacts'][$kind][] = $path;
        }
    }

    private function releaseLock(string $runId): void
    {
        $path = $this->lockPath($runId);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function buildRunId(string $lstsaId): string
    {
        $safe = $this->safeSegment($lstsaId);
        return $safe . '_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
    }

    private function assertCleanId(string $value, string $name): void
    {
        if ($value === '' || !preg_match('/^[A-Za-z0-9_.-]+$/', $value)) {
            throw new \InvalidArgumentException('Invalid ' . $name . ': ' . $value);
        }
    }

    private function safeSegment(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_.-]/', '_', $value) ?? '';
        $safe = trim($safe, '._-');
        if ($safe === '') {
            throw new \InvalidArgumentException('Invalid LSTSA path segment');
        }
        return $safe;
    }

    private function safeSuffix(string $suffix): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_.-]/', '_', $suffix) ?? '';
        $safe = trim($safe, '._-');
        if ($safe === '' || !str_ends_with($safe, '.json')) {
            throw new \InvalidArgumentException('Invalid LSTSA artifact suffix: ' . $suffix);
        }
        return $safe;
    }

    private function now(): string
    {
        return gmdate('c');
    }

    private function writeJson(string $path, array $payload): void
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Cannot encode JSON for: ' . $path);
        }

        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create directory: ' . $dir);
        }

        file_put_contents($path, $json . PHP_EOL, LOCK_EX);
    }

    private function readJson(string $path): mixed
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Cannot read JSON file: ' . $path);
        }

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
