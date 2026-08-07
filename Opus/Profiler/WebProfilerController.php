<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Http\Request;
use Opus\Http\Response;

/** Serves one persisted OPUS trace after bootstrap-level Web Profiler registration. */
final class WebProfilerController implements WebProfilerControllerInterface
{
    public function __construct(
        private readonly ProfilerInterface $profiler,
        private readonly WebProfilerViewInterface $view
    ) {
    }

    public function handle(Request $request): Response
    {
        $path = trim($request->path, '/');
        if (preg_match(
            '~^_opus/profiler/trace/([a-f0-9]{16,64})$~D',
            $path,
            $match
        ) !== 1) {
            throw new \RuntimeException('OPUS_PROFILER_ROUTE_NOT_FOUND');
        }

        $records = $this->profiler->readTrace($match[1]);
        $trace = $records[array_key_last($records)] ?? null;
        if (!is_array($trace)) {
            throw new \RuntimeException('OPUS_PROFILER_TRACE_RECORD_INVALID');
        }

        return Response::html($this->view->renderTrace($trace));
    }
}
