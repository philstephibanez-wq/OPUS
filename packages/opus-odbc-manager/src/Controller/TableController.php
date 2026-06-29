<?php
declare(strict_types=1);

namespace OpusOdbcManager\Controller;

use OpusOdbcManager\Diagnostics\OdbcManagerProfiler;
use OpusOdbcManager\View\OdbcManagerReadOnlyViewModelFactory;

/**
 * Table detail endpoint for column/model inspection.
 */
final class TableController
{
    private OdbcManagerReadOnlyViewModelFactory $views;
    private OdbcManagerProfiler $profiler;

    public function __construct(?OdbcManagerReadOnlyViewModelFactory $views = null, ?OdbcManagerProfiler $profiler = null)
    {
        $this->views = $views ?? new OdbcManagerReadOnlyViewModelFactory();
        $this->profiler = $profiler ?? OdbcManagerProfiler::disabled();
    }

    /** @return array<string,mixed> */
    public function detail(string $table = ''): array
    {
        return $this->profiler->profile('table_detail', ['controller' => self::class, 'table' => $table], function () use ($table): array {
            return $this->views->tableDetail($table);
        });
    }
}
