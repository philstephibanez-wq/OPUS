<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Http\Request;
use Opus\Http\Response;

/** Serves one persisted OPUS trace through the generic SCORE profiler view. */
final class WebProfilerController implements WebProfilerControllerInterface
{
    public function __construct(
        private readonly ProfilerInterface $profiler,
        private readonly WebProfilerViewInterface $view,
        private readonly bool $accessGranted = false
    ) {
    }

    public function handle(Request $request): Response
    {
        $environment = strtolower(trim((string) getenv('OPUS_ENV')));
        if (!in_array($environment, ['dev', 'local', 'development'], true)) {
            throw new \RuntimeException('OPUS_PROFILER_ENVIRONMENT_FORBIDDEN');
        }
        if (!$this->accessGranted) {
            throw new \RuntimeException('OPUS_PROFILER_ACCESS_DENIED');
        }
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
