<?php
declare(strict_types=1);

namespace OpusLstsarManager\Controller;

use OpusLstsarManager\Diagnostics\LstsarManagerProfiler;
use OpusLstsarManager\View\LstsarManagerViewModelFactory;

/**
 * Dashboard endpoint for the protected OPUS LSTSAR Manager application.
 */
final class DashboardController
{
    private LstsarManagerViewModelFactory $views;
    private LstsarManagerProfiler $profiler;

    public function __construct(?LstsarManagerViewModelFactory $views = null, ?LstsarManagerProfiler $profiler = null)
    {
        $this->views = $views ?? new LstsarManagerViewModelFactory();
        $this->profiler = $profiler ?? LstsarManagerProfiler::disabled();
    }

    /** @return array<string,mixed> */
    public function dashboard(): array
    {
        return $this->profiler->profile('dashboard', ['controller' => self::class], function (): array {
            return $this->views->dashboard();
        });
    }
}
