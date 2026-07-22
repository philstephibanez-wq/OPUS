<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Framework\OpusFrameworkComponentInterface;

interface WebProfilerViewInterface extends OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function renderIndex(array $traces, array $fsmMaps): string;
    public function renderTrace(array $trace, array $fsmMaps): string;
}