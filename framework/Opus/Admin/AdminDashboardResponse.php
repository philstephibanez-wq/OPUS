<?php

declare(strict_types=1);

namespace Opus\Admin;

use InvalidArgumentException;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Represent a rendered native OPUS administrator dashboard response.
 *
 * Responsibility:
 *   Carry administrator-only response status, headers and body after the admin
 *   dashboard route control plane has authorized access.
 *
 * Contract:
 *   This response is admin-only. It must never be reused as a public response,
 *   and it must only be created after FSM/ACL/identity control has allowed the
 *   dashboard route.
 */
final class AdminDashboardResponse
{
    /** @param array<string,string> $headers */
    public function __construct(
        private readonly int $statusCode,
        private readonly string $body,
        private readonly array $headers
    ) {
        if ($this->statusCode < 100 || $this->statusCode > 599) {
            throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_RESPONSE_STATUS_INVALID');
        }

        if ($this->body === '') {
            throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_RESPONSE_BODY_EMPTY');
        }

        foreach ($this->headers as $name => $value) {
            if (!is_string($name) || $name === '' || !is_string($value) || $value === '') {
                throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_RESPONSE_HEADER_INVALID');
            }
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
