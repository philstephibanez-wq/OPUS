<?php

declare(strict_types=1);

namespace ASAP\Lstsa;

/**
 * PUBLIC Lstsa REPORT CATALOG
 *
 * @role Builds a readable append-only catalog of Lstsa runs, reports, archives,
 *       quarantine files and checkpoints.
 * @visibility public framework service
 * @contract Runtime outputs are read from var/lstsa and summarized without
 *           mutating existing run reports or artifacts.
 * @invariant Catalog files are timestamped and never overwrite previous catalogs.
 * @sideEffects Writes JSON and Markdown catalog snapshots under var/lstsa/reports/_index.
 */
final class LstsaReportCatalog
{
    private string $projectRoot;
    private string $lstsaRoot;

    public function __construct(string $projectRoot)
    {
        $root = rtrim($projectRoot, DIRECTORY_SEPARATOR);
        if ($root === '' || !is_dir($root)) {
            throw new \RuntimeException('Invalid ASAP project root for Lstsa catalog: ' . $projectRoot);
        }

        $this->projectRoot = $root;
        $this->lstsaRoot = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'lstsa';
    }

    /**
     * @param int $limit Maximum number of recent runs to include in the visible catalog.
     * @return array<string,mixed>
     */
    public function build(int $limit = 50): array
    {
        if ($limit < 1) {
            throw new \InvalidArgumentException('Lstsa catalog limit must be >= 1');
        }

        $runs = $this->readRuns();
        usort($runs, static function (array $a, array $b): int {
            $ad = (string)($a['finished_at'] ?? $a['updated_at'] ?? $a['created_at'] ?? '');
            $bd = (string)($b['finished_at'] ?? $b['updated_at'] ?? $b['created_at'] ?? '');
            return strcmp($bd, $ad);
        });

        $statusCounts = [];
        $lstsaCounts = [];
        foreach ($runs as $run) {
            $status = (string)($run['status'] ?? 'UNKNOWN');
            $lstsaId = (string)($run['lstsa_id'] ?? 'UNKNOWN');
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            $lstsaCounts[$lstsaId] = ($lstsaCounts[$lstsaId] ?? 0) + 1;
        }
        ksort($statusCounts);
        ksort($lstsaCounts);

        $visibleRuns = [];
        foreach (array_slice($runs, 0, $limit) as $run) {
            $visibleRuns[] = $this->summarizeRun($run);
        }

        return [
            'schema' => 'ASAP_Lstsa_REPORT_CATALOG_V1',
            'generated_at' => gmdate('c'),
            'project_root' => $this->projectRoot,
            'lstsa_root' => $this->lstsaRoot,
            'total_runs' => count($runs),
            'visible_limit' => $limit,
            'status_counts' => $statusCounts,
            'lstsa_counts' => $lstsaCounts,
            'runs' => $visibleRuns,
        ];
    }

    /**
     * @return array{json:string,markdown:string,index:array<string,mixed>}
     */
    public function writeIndex(int $limit = 50): array
    {
        $index = $this->build($limit);
        $dir = $this->lstsaRoot . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . '_index';

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create Lstsa report catalog directory: ' . $dir);
        }

        $base = 'lstsa_report_catalog_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $jsonPath = $dir . DIRECTORY_SEPARATOR . $base . '.json';
        $mdPath = $dir . DIRECTORY_SEPARATOR . $base . '.md';

        foreach ([$jsonPath, $mdPath] as $path) {
            if (file_exists($path)) {
                throw new \RuntimeException('Lstsa report catalog append-only violation: ' . $path);
            }
        }

        $this->writeJson($jsonPath, $index);
        $this->writeText($mdPath, $this->toMarkdown($index));

        return [
            'json' => $jsonPath,
            'markdown' => $mdPath,
            'index' => $index,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function readRuns(): array
    {
        $queueDir = $this->lstsaRoot . DIRECTORY_SEPARATOR . 'queue';
        if (!is_dir($queueDir)) {
            return [];
        }

        $runs = [];
        foreach (glob($queueDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
            $payload = $this->readJson($path);
            if (is_array($payload) && isset($payload['run_id'])) {
                $runs[] = $payload;
            }
        }

        return $runs;
    }

    /**
     * @param array<string,mixed> $run
     * @return array<string,mixed>
     */
    private function summarizeRun(array $run): array
    {
        $artifacts = is_array($run['artifacts'] ?? null) ? $run['artifacts'] : [];
        $artifactSummary = [];
        $missingArtifacts = [];

        foreach ($artifacts as $kind => $paths) {
            $paths = is_array($paths) ? $paths : [];
            $existing = 0;
            foreach ($paths as $path) {
                $path = (string)$path;
                if (is_file($path)) {
                    ++$existing;
                    continue;
                }
                $missingArtifacts[] = [
                    'kind' => (string)$kind,
                    'path' => $path,
                ];
            }
            $artifactSummary[(string)$kind] = [
                'declared' => count($paths),
                'existing' => $existing,
                'missing' => count($paths) - $existing,
            ];
        }

        $reportJson = (string)($run['report_json'] ?? '');
        $reportMd = (string)($run['report_md'] ?? '');

        return [
            'run_id' => (string)($run['run_id'] ?? ''),
            'lstsa_id' => (string)($run['lstsa_id'] ?? ''),
            'status' => (string)($run['status'] ?? ''),
            'requested_by' => $run['requested_by'] ?? null,
            'runner_id' => $run['runner_id'] ?? null,
            'current_step' => $run['current_step'] ?? null,
            'created_at' => $run['created_at'] ?? null,
            'started_at' => $run['started_at'] ?? null,
            'finished_at' => $run['finished_at'] ?? null,
            'counts' => is_array($run['counts'] ?? null) ? $run['counts'] : [],
            'report_json' => $reportJson,
            'report_json_exists' => $reportJson !== '' && is_file($reportJson),
            'report_md' => $reportMd,
            'report_md_exists' => $reportMd !== '' && is_file($reportMd),
            'artifact_summary' => $artifactSummary,
            'missing_artifacts' => $missingArtifacts,
            'error' => $run['error'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $index
     */
    private function toMarkdown(array $index): string
    {
        $lines = [];
        $lines[] = '# Lstsa report catalog';
        $lines[] = '';
        $lines[] = '- generated_at: `' . (string)$index['generated_at'] . '`';
        $lines[] = '- total_runs: `' . (string)$index['total_runs'] . '`';
        $lines[] = '- visible_limit: `' . (string)$index['visible_limit'] . '`';
        $lines[] = '';
        $lines[] = '## Status counts';
        $lines[] = '';
        foreach (($index['status_counts'] ?? []) as $status => $count) {
            $lines[] = '- ' . (string)$status . ': `' . (string)$count . '`';
        }
        $lines[] = '';
        $lines[] = '## Lstsa counts';
        $lines[] = '';
        foreach (($index['lstsa_counts'] ?? []) as $lstsaId => $count) {
            $lines[] = '- ' . (string)$lstsaId . ': `' . (string)$count . '`';
        }
        $lines[] = '';
        $lines[] = '## Runs';
        $lines[] = '';
        foreach (($index['runs'] ?? []) as $run) {
            if (!is_array($run)) {
                continue;
            }
            $lines[] = '### `' . (string)($run['run_id'] ?? '') . '`';
            $lines[] = '';
            $lines[] = '- lstsa_id: `' . (string)($run['lstsa_id'] ?? '') . '`';
            $lines[] = '- status: `' . (string)($run['status'] ?? '') . '`';
            $lines[] = '- current_step: `' . (string)($run['current_step'] ?? '') . '`';
            $lines[] = '- finished_at: `' . (string)($run['finished_at'] ?? '') . '`';
            $lines[] = '- report_json_exists: `' . (($run['report_json_exists'] ?? false) ? 'yes' : 'no') . '`';
            $lines[] = '- report_md_exists: `' . (($run['report_md_exists'] ?? false) ? 'yes' : 'no') . '`';
            $lines[] = '';
            $lines[] = '#### Counts';
            foreach (($run['counts'] ?? []) as $name => $value) {
                $lines[] = '- ' . (string)$name . ': `' . (string)$value . '`';
            }
            $lines[] = '';
            $lines[] = '#### Artifacts';
            foreach (($run['artifact_summary'] ?? []) as $kind => $summary) {
                if (!is_array($summary)) {
                    continue;
                }
                $lines[] = '- ' . (string)$kind . ': declared `' . (string)($summary['declared'] ?? 0) . '`, existing `' . (string)($summary['existing'] ?? 0) . '`, missing `' . (string)($summary['missing'] ?? 0) . '`';
            }
            $lines[] = '';
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @return mixed
     */
    private function readJson(string $path): mixed
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Cannot read Lstsa catalog JSON source: ' . $path);
        }

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function writeJson(string $path, array $payload): void
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Cannot encode Lstsa report catalog JSON');
        }

        $this->writeText($path, $json . PHP_EOL);
    }

    private function writeText(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create Lstsa report catalog directory: ' . $dir);
        }

        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new \RuntimeException('Cannot write Lstsa report catalog file: ' . $path);
        }
    }
}
