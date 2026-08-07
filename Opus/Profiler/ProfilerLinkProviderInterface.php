<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Framework\OpusExceptionAwareInterface;
use Opus\Framework\OpusFrameworkComponentInterface;
use Opus\Framework\OpusProfilerAwareInterface;
use Opus\Framework\OpusSelfDocumentingInterface;

/** Contract for generic Profiler diagnostics injection into a SCORE ViewModel. */
interface ProfilerLinkProviderInterface extends
    OpusFrameworkComponentInterface,
    OpusExceptionAwareInterface,
    OpusProfilerAwareInterface,
    OpusSelfDocumentingInterface
{
    /** @param array<string,mixed> $viewModel @return array<string,mixed> */
    public function enrich(array $viewModel): array;
}
