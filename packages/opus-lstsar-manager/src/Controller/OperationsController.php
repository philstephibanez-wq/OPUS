<?php
declare(strict_types=1);

namespace OpusLstsarManager\Controller;

use OpusLstsarManager\Diagnostics\LstsarManagerProfiler;
use OpusLstsarManager\View\LstsarManagerViewModelFactory;

/**
 * Site/client-scoped LSTSAR operations dashboard endpoint.
 */
final class OperationsController
{
    private LstsarManagerViewModelFactory $views;
    private LstsarManagerProfiler $profiler;

    public function __construct(?LstsarManagerViewModelFactory $views = null, ?LstsarManagerProfiler $profiler = null)
    {
        $this->views = $views ?? new LstsarManagerViewModelFactory();
        $this->profiler = $profiler ?? LstsarManagerProfiler::disabled();
    }

    /** @return array<string,mixed> */
    public function operations(string $siteId = 'site-demo'): array
    {
        return $this->profiler->profile('operations', ['controller' => self::class, 'site_id' => $siteId], function () use ($siteId): array {
            return $this->views->operations($siteId);
        });
    }
}
