<?php
declare(strict_types=1);

namespace OpusOdbcManager\Controller;

use OpusOdbcManager\Diagnostics\OdbcManagerProfiler;
use OpusOdbcManager\View\OdbcManagerReadOnlyViewModelFactory;

/**
 * Data source inventory endpoint for the read-only ODBC Manager site.
 */
final class DataSourcesController
{
    private OdbcManagerReadOnlyViewModelFactory $views;
    private OdbcManagerProfiler $profiler;

    public function __construct(?OdbcManagerReadOnlyViewModelFactory $views = null, ?OdbcManagerProfiler $profiler = null)
    {
        $this->views = $views ?? new OdbcManagerReadOnlyViewModelFactory();
        $this->profiler = $profiler ?? OdbcManagerProfiler::disabled();
    }

    /** @return array<string,mixed> */
    public function datasources(): array
    {
        return $this->profiler->profile('datasources', ['controller' => self::class], function (): array {
            return $this->views->datasources();
        });
    }
}
