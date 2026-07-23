<?php
declare(strict_types=1);

namespace Opus\Http;

/**
 * HTTP response value object emitted by the OPUS runtime.
 *
 * Stores status code, headers and body content before the front controller
 * writes the response through the output stream. UI emission never uses echo.
 */
final class Response implements ResponseInterface
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
        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
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

        $stream = fopen('php://output', 'wb');
        if ($stream === false) {
            throw new \RuntimeException('OPUS_HTTP_OUTPUT_STREAM_OPEN_FAILED');
        }

        try {
            $length = strlen($this->body);
            $offset = 0;
            while ($offset < $length) {
                $written = fwrite($stream, substr($this->body, $offset));
                if ($written === false || $written < 1) {
                    throw new \RuntimeException('OPUS_HTTP_OUTPUT_STREAM_WRITE_FAILED');
                }
                $offset += $written;
            }
        } finally {
            fclose($stream);
        }
    }
}
