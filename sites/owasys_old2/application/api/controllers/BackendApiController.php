<?php
declare(strict_types=1);

use Opus\Http\Request;
use Opus\Http\Response;
use Opus\Rcp\Rest\RcpRestServer;

/** OWASYS application controller exposing its secured REST/Composer backend. */
final class OwasysBackendApiController
{
    private ?Request $request = null;

    public function __construct(
        private readonly string $siteRoot,
        private readonly string $opusRoot
    ) {
    }

    public function matchesCurrentRequest(): bool
    {
        $path = '/' . trim($this->request()->path, '/');
        return $path === '/api' || str_starts_with($path, '/api/');
    }

    public function run(): void
    {
        try {
            RcpRestServer::fromRoot(
                $this->opusRoot,
                'sites/owasys/config/backend.rest.json'
            )->handle($this->request())->send();
        } catch (\Throwable $cause) {
            Response::json([
                'contract' => 'OPUS_RCP_REST_ERROR_V1',
                'status' => 'failed',
                'error_code' => $this->errorCode($cause),
            ], 503)->send();
        }
    }

    private function errorCode(\Throwable $cause): string
    {
        $message = trim($cause->getMessage());
        return preg_match('/^[A-Z0-9_:-]{3,240}$/', $message) === 1
            ? $message
            : 'OWASYS_BACKEND_INITIALIZATION_FAILED';
    }

    private function request(): Request
    {
        return $this->request ??= Request::fromGlobals($this->opusRoot);
    }
}
