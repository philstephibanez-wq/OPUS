<?php
declare(strict_types=1);

namespace Opus\MIDDLE\FSM;

final class FsmResult
{
    private function __construct(
        private readonly bool $allowed,
        private readonly string $state,
        private readonly string $message
    ) {
    }

    public static function allowed(string $state, string $message = 'FSM_ALLOWED'): self
    {
        return new self(true, $state, $message);
    }

    public static function denied(string $state, string $message = 'FSM_DENIED'): self
    {
        return new self(false, $state, $message);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function message(): string
    {
        return $this->message;
    }
}
