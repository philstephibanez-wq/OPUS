<?php
declare(strict_types=1);

namespace ASAP;

final class Response
{
    private string $body;
    private int $status;
    /** @var array<string,string> */
    private array $headers;

    private function __construct(string $body, int $status, array $headers)
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /** @param mixed $payload */
    public static function json($payload, int $status = 200): self
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($body)) {
            $body = '{"error":"json_encode failed"}';
            $status = 500;
        }
        return new self($body, $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo $this->body;
    }
}
