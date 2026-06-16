<?php

declare(strict_types=1);

namespace Opus\Http;

use InvalidArgumentException;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Represent a public response emitted by the OPUS public MVC smoke pipeline.
 *
 * Responsibility:
 *   Carry public status, headers and body without internal diagnostics.
 *
 * Contract:
 *   PublicResponse is safe for public delivery. Internal details, blocked-state
 *   names, FSM transitions, ACL rules, class names, filesystem paths and stack
 *   traces must never be stored in this object.
 */
final class PublicResponse
{
    /** @param array<string,string> $headers */
    public function __construct(
        private readonly int $statusCode,
        private readonly string $body,
        private readonly array $headers = []
    ) {
        if ($this->statusCode < 100 || $this->statusCode > 599) {
            throw new InvalidArgumentException('OPUS_PUBLIC_RESPONSE_STATUS_INVALID');
        }
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    /** @return array{status:int,headers:array<string,string>,body:string} */
    public function toArray(): array
    {
        return [
            'status' => $this->statusCode,
            'headers' => $this->headers,
            'body' => $this->body,
        ];
    }
}
