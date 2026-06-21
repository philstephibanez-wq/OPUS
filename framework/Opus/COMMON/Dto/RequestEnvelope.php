<?php
declare(strict_types=1);

namespace Opus\COMMON\Dto;

final class RequestEnvelope
{
    public function __construct(
        private readonly string $requestId,
        private readonly string $method,
        private readonly string $path
    ) {
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }
}
