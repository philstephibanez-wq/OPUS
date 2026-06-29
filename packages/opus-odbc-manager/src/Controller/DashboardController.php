<?php
declare(strict_types=1);

namespace OpusOdbcManager\Controller;

use OpusOdbcManager\Diagnostics\OdbcManagerProfiler;
use OpusOdbcManager\View\OdbcManagerReadOnlyViewModelFactory;

/**
 * Dashboard endpoint for the protected OPUS ODBC Manager site application.
 */
final class DashboardController
{
    private OdbcManagerReadOnlyViewModelFactory $views;
    private OdbcManagerProfiler $profiler;

    public function __construct(?OdbcManagerReadOnlyViewModelFactory $views = null, ?OdbcManagerProfiler $profiler = null)
    {
        $this->views = $views ?? new OdbcManagerReadOnlyViewModelFactory();
        $this->profiler = $profiler ?? OdbcManagerProfiler::disabled();
    }

    /** @return array<string,mixed> */
    public function dashboard(): array
    {
        return $this->profiler->profile('dashboard', ['controller' => self::class], function (): array {
            return $this->views->dashboard();
        });
    }
}
