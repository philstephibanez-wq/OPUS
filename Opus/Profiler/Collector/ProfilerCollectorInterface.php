<?php
declare(strict_types=1);

namespace Opus\Profiler\Collector;

use Opus\Framework\OpusFrameworkComponentInterface;

interface ProfilerCollectorInterface extends OpusFrameworkComponentInterface
{
    public function category(): string;
    public function label(): string;
    public function collect(array $trace): array;
}