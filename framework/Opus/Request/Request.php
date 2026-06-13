<?php
declare(strict_types=1);
namespace Opus\Request;
use ASAP\Http\Request as HttpRequest;
/*
 * OPUS_REFBOOK:
 *   domain: REQUEST
 *   role: Class Request belongs to the REQUEST Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the REQUEST domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - request-overview
 *   diagrams:
 *     - request-runtime
 * END_OPUS_REFBOOK
 */
final class Request
{
    public function __construct(public readonly string $path, public readonly string $method = 'GET') { if ($this->path === '' || $this->path[0] !== '/') { throw new \InvalidArgumentException('OPUS_REQUEST_PATH_INVALID'); } }
    public function toHttpRequest(): HttpRequest { return new HttpRequest($this->path, $this->method); }
}
