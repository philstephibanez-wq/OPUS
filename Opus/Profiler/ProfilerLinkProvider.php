<?php
declare(strict_types=1);

namespace Opus\Profiler;

/** Injects the current trace link into the generic SCORE diagnostics ViewModel slot. */
final class ProfilerLinkProvider implements ProfilerLinkProviderInterface
{
    private const TRACE_ID_PATTERN = '/^[a-f0-9]{16,64}$/D';

    public function __construct(
        private readonly ProfilerInterface $profiler,
        private readonly string $label = 'OPUS Profiler'
    ) {
    }

    public function enrich(array $viewModel): array
    {
        $diagnostics = is_array($viewModel['diagnostics'] ?? null)
            ? $viewModel['diagnostics']
            : [];
        $diagnostics['profiler_available'] = false;
        $diagnostics['profiler_url'] = '';
        $diagnostics['profiler_label'] = $this->label;

        $traceId = $this->profiler->getActiveTrace()?->getTraceId() ?? '';
        if (preg_match(self::TRACE_ID_PATTERN, $traceId) === 1) {
            $diagnostics['profiler_available'] = true;
            $diagnostics['profiler_url'] = '/_opus/profiler/trace/' . $traceId;
        }

        $viewModel['diagnostics'] = $diagnostics;
        return $viewModel;
    }
}
