<?php
declare(strict_types=1);

namespace Opus\COMMON\Error;

final class TypedError
{
    public function __construct(
        private readonly string $code,
        private readonly string $message
    ) {
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }
}
