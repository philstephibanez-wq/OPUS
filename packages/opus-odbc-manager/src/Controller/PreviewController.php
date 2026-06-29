<?php
declare(strict_types=1);

namespace OpusOdbcManager\Controller;

use OpusOdbcManager\Diagnostics\OdbcManagerProfiler;
use OpusOdbcManager\View\OdbcManagerReadOnlyViewModelFactory;

/**
 * Read-only row preview endpoint with an enforced display limit.
 */
final class PreviewController
{
    private OdbcManagerReadOnlyViewModelFactory $views;
    private OdbcManagerProfiler $profiler;

    public function __construct(?OdbcManagerReadOnlyViewModelFactory $views = null, ?OdbcManagerProfiler $profiler = null)
    {
        $this->views = $views ?? new OdbcManagerReadOnlyViewModelFactory();
        $this->profiler = $profiler ?? OdbcManagerProfiler::disabled();
    }

    /** @return array<string,mixed> */
    public function preview(string $table = '', int $limit = 20): array
    {
        return $this->profiler->profile('preview', ['controller' => self::class, 'table' => $table, 'limit' => $limit], function () use ($table, $limit): array {
            return $this->views->preview($table, $limit);
        });
    }
}
