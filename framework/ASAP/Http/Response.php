<?php

declare(strict_types=1);

namespace ASAP\Http;

use ASAP\Contract\ContractException;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Carry HTTP response data.
 *
 * Responsibility:
 *   Emit status, headers and body from a validated controller result.
 *
 * Contract:
 *   Response emits representation only. It does not route, authorize or mutate state.
 *
 * Since:
 *   P112D1
 *
 * Legacy compatibility:
 *   P112O restores static html()/json() constructors.
 */
final class Response
{
    /**
     * @param string $body Response body.
     * @param int $status HTTP status code.
     * @param array<string,string> $headers HTTP headers.
     */
    public function __construct(
        public readonly string $body,
        public readonly int $status = 200,
        public readonly array $headers = ['Content-Type' => 'text/html; charset=utf-8']
    ) {
        if ($this->status < 100 || $this->status > 599) {
            throw ContractException::because('ASAP_RESPONSE_STATUS_INVALID', (string) $this->status);
        }
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /** @param mixed $data JSON-serializable data. */
    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}
