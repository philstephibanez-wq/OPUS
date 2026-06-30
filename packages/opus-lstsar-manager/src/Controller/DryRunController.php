<?php
declare(strict_types=1);

namespace OpusLstsarManager\Controller;

use OpusLstsarManager\Diagnostics\LstsarManagerProfiler;
use OpusLstsarManager\View\LstsarManagerViewModelFactory;

/**
 * Dry-run endpoints connected to the real model-driven ODBC LSTSAR engine.
 *
 * The endpoints remain dry-run only: no direct execution, no DDL and no raw SQL.
 */
final class DryRunController
{
    private LstsarManagerViewModelFactory $views;
    private LstsarManagerProfiler $profiler;

    public function __construct(?LstsarManagerViewModelFactory $views = null, ?LstsarManagerProfiler $profiler = null)
    {
        $this->views = $views ?? new LstsarManagerViewModelFactory();
        $this->profiler = $profiler ?? LstsarManagerProfiler::disabled();
    }

    /** @return array<string,mixed> */
    public function dryRunForm(): array
    {
        return $this->profiler->profile('dry_run_form', ['controller' => self::class], fn (): array => $this->views->dryRun());
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function preview(array $payload = []): array
    {
        return $this->profiler->profile('dry_run_preview', [
            'controller' => self::class,
            'payload_keys' => array_keys($payload),
            'engine_integrated' => true,
        ], fn (): array => $this->views->dryRun($payload));
    }
}
