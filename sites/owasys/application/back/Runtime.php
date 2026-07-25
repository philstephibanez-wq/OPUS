<?php
declare(strict_types=1);

use Opus\Http\Response;

/** Isolated OWASYS REST/Composer backend runtime. */
final class OwasysBackRuntime implements OwasysProcessRuntimeInterface
{
    private readonly OwasysBackendApiController $controller;

    public function __construct(string $siteRoot)
    {
        $this->controller = new OwasysBackendApiController(
            $siteRoot,
            dirname(dirname($siteRoot))
        );
    }

    public function mode(): string
    {
        return 'back';
    }

    public function run(): void
    {
        if (!$this->controller->matchesCurrentRequest()) {
            throw new RuntimeException('OWASYS_BACK_RUNTIME_ROUTE_FORBIDDEN');
        }
        $this->controller->run();
    }

    public function fail(Throwable $error, string $traceId): void
    {
        $message = trim($error->getMessage());
        $code = preg_match('/^[A-Z0-9_:-]{3,240}$/', $message) === 1
            ? $message
            : 'OWASYS_BACK_RUNTIME_FAILED';
        Response::json([
            'contract' => 'OWASYS_BACK_RUNTIME_ERROR_V1',
            'status' => 'failed',
            'error_code' => $code,
            'trace_id' => $traceId,
        ], str_contains($code, 'ROUTE_FORBIDDEN') ? 404 : 500)->send();
    }
}
