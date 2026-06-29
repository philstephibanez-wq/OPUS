<?php
declare(strict_types=1);

namespace OpusOdbcManager\Controller;

use OpusOdbcManager\Diagnostics\OdbcManagerProfiler;
use OpusOdbcManager\View\OdbcManagerReadOnlyViewModelFactory;

/**
 * LSTSAR draft endpoint for a selected ODBC table.
 */
final class LstsarDraftController
{
    private OdbcManagerReadOnlyViewModelFactory $views;
    private OdbcManagerProfiler $profiler;

    public function __construct(?OdbcManagerReadOnlyViewModelFactory $views = null, ?OdbcManagerProfiler $profiler = null)
    {
        $this->views = $views ?? new OdbcManagerReadOnlyViewModelFactory();
        $this->profiler = $profiler ?? OdbcManagerProfiler::disabled();
    }

    /** @return array<string,mixed> */
    public function draft(string $table = ''): array
    {
        return $this->profiler->profile('lstsar_draft', ['controller' => self::class, 'table' => $table], function () use ($table): array {
            return $this->views->lstsarDraft($table);
        });
    }
}
