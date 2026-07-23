<?php
declare(strict_types=1);

use Opus\Http\Request;
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
        RcpRestServer::fromRoot(
            $this->opusRoot,
            'sites/owasys/config/backend.rest.json'
        )->handle($this->request())->send();
    }

    private function request(): Request
    {
        return $this->request ??= Request::fromGlobals($this->opusRoot);
    }
}
