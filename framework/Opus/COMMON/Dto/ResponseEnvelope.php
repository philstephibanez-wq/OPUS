<?php
declare(strict_types=1);

namespace Opus\COMMON\Dto;

final class ResponseEnvelope
{
    public function __construct(
        private readonly string $requestId,
        private readonly int $statusCode,
        private readonly string $contractId
    ) {
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function contractId(): string
    {
        return $this->contractId;
    }
}
