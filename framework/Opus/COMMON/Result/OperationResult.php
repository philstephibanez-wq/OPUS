<?php
declare(strict_types=1);

namespace Opus\COMMON\Result;

use Opus\COMMON\Error\TypedError;

final class OperationResult
{
    private function __construct(
        private readonly bool $ok,
        private readonly ?TypedError $error = null
    ) {
    }

    public static function ok(): self
    {
        return new self(true);
    }

    public static function failed(TypedError $error): self
    {
        return new self(false, $error);
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function error(): ?TypedError
    {
        return $this->error;
    }
}
