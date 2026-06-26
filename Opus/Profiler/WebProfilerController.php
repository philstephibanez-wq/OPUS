<?php
declare(strict_types=1);

namespace Opus\Profiler;

use Opus\Fsm\Runtime\FsmRuntimeConfigLoader;
use Opus\Http\Request;
use Opus\Http\Response;

final class WebProfilerController implements WebProfilerControllerInterface
{
    private TraceFileRepository $repository;
    private WebProfilerView $view;
    private FsmRuntimeConfigLoader $fsmRuntimeConfigLoader;

    public function __construct(string $rootDir, FsmRuntimeConfigLoader $fsmRuntimeConfigLoader)
    {
        $this->repository = new TraceFileRepository(rtrim(str_replace('\\', '/', $rootDir), '/') . '/var/profiler');
        $this->view = new WebProfilerView();
        $this->fsmRuntimeConfigLoader = $fsmRuntimeConfigLoader;
    }

    public function handle(Request $request): Response
    {
        $path = trim($request->path, '/');
        if (preg_match('~^_opus/profiler/trace/([A-Za-z0-9_.\-]+)$~', $path, $m) === 1) {
            return Response::html($this->view->renderTrace($this->repository->readTrace($m[1]), $this->fsmRuntimeConfigLoader->availableMaps()));
        }
        return Response::html($this->view->renderIndex($this->repository->listTraces(), $this->fsmRuntimeConfigLoader->availableMaps()));
    }
}