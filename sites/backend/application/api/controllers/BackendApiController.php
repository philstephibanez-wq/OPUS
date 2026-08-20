<?php
declare(strict_types=1);

use Opus\Api\Rest\RestServer;
use Opus\Http\Request;

final class BackendBackendApiController implements BackendBackendApiControllerInterface
{
    private ?Request $request = null;

    public function __construct(
        private readonly string $siteRoot,
        private readonly string $opusRoot
    ) {
    }

    public function matchesCurrentRequest(): bool
    {
        return str_starts_with('/' . trim($this->request()->path, '/'), '/api/');
    }

    public function run(): void
    {
        RestServer::fromRoot($this->opusRoot, 'sites/backend/config/backend.rest.json')
            ->handle($this->request())->send();
    }

    private function request(): Request
    {
        return $this->request ??= Request::fromGlobals($this->opusRoot);
    }
}