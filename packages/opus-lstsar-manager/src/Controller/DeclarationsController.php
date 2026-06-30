<?php
declare(strict_types=1);

namespace OpusLstsarManager\Controller;

use OpusLstsarManager\Diagnostics\LstsarManagerProfiler;
use OpusLstsarManager\View\LstsarManagerViewModelFactory;

/**
 * Declaration screens for sources, destinations, models, mappings and policies.
 */
final class DeclarationsController
{
    private LstsarManagerViewModelFactory $views;
    private LstsarManagerProfiler $profiler;

    public function __construct(?LstsarManagerViewModelFactory $views = null, ?LstsarManagerProfiler $profiler = null)
    {
        $this->views = $views ?? new LstsarManagerViewModelFactory();
        $this->profiler = $profiler ?? LstsarManagerProfiler::disabled();
    }

    /** @return array<string,mixed> */
    public function declarations(): array
    {
        return $this->profiler->profile('declarations', ['controller' => self::class], fn (): array => $this->views->declarations());
    }

    /** @return array<string,mixed> */
    public function sources(): array
    {
        return $this->profiler->profile('sources', ['controller' => self::class], fn (): array => $this->views->endpoint('source'));
    }

    /** @return array<string,mixed> */
    public function destinations(): array
    {
        return $this->profiler->profile('destinations', ['controller' => self::class], fn (): array => $this->views->endpoint('destination'));
    }

    /** @return array<string,mixed> */
    public function mappings(): array
    {
        return $this->profiler->profile('mappings', ['controller' => self::class], fn (): array => $this->views->mappings());
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return $this->profiler->profile('rules', ['controller' => self::class], fn (): array => $this->views->rules());
    }

    /** @return array<string,mixed> */
    public function archiveReport(): array
    {
        return $this->profiler->profile('archive_report', ['controller' => self::class], fn (): array => $this->views->archiveReport());
    }
}
