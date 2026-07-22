<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Http\Request;
use Opus\Http\Response;

interface WebProfilerControllerInterface extends OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function handle(Request $request): Response;
}