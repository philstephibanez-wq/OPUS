<?php
declare(strict_types=1);
namespace ASAP\RESPONSE;
use ASAP\Http\Response as HttpResponse;
final class Response
{
    public function __construct(public readonly string $body, public readonly int $status = 200, public readonly array $headers = []) { if ($this->status < 100 || $this->status > 599) { throw new \InvalidArgumentException('ASAP_RESPONSE_STATUS_INVALID'); } }
    public function toHttpResponse(): HttpResponse { return new HttpResponse($this->body, $this->status, $this->headers); }
}
