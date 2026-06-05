<?php
declare(strict_types=1);
namespace ASAP\REQUEST;
use ASAP\Http\Request as HttpRequest;
final class Request
{
    public function __construct(public readonly string $path, public readonly string $method = 'GET') { if ($this->path === '' || $this->path[0] !== '/') { throw new \InvalidArgumentException('ASAP_REQUEST_PATH_INVALID'); } }
    public function toHttpRequest(): HttpRequest { return new HttpRequest($this->path, $this->method); }
}
