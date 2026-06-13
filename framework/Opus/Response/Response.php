<?php
declare(strict_types=1);
namespace Opus\Response;
use ASAP\Http\Response as HttpResponse;
/*
 * OPUS_REFBOOK:
 *   domain: RESPONSE
 *   role: Class Response belongs to the RESPONSE Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the RESPONSE domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - response-overview
 *   diagrams:
 *     - response-runtime
 * END_OPUS_REFBOOK
 */
final class Response
{
    public function __construct(public readonly string $body, public readonly int $status = 200, public readonly array $headers = []) { if ($this->status < 100 || $this->status > 599) { throw new \InvalidArgumentException('OPUS_RESPONSE_STATUS_INVALID'); } }
    public function toHttpResponse(): HttpResponse { return new HttpResponse($this->body, $this->status, $this->headers); }
}
