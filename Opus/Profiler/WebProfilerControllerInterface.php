<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Http\Request;
use Opus\Http\Response;

interface WebProfilerControllerInterface extends OpusFrameworkComponentInterface
{
    public function handle(Request $request): Response;
}