<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * One deterministic LSTSAR contract violation.
 */
final class LstsarViolation
{
    private string $stage;
    private string $field;
    private string $code;
    private string $message;

    /** @var array<string,mixed> */
    private array $context;

    /** @param array<string,mixed> $context */
    public function __construct(string $stage, string $field, string $code, string $message, array $context = [])
    {
        $this->stage = $stage;
        $this->field = $field;
        $this->code = $code;
        $this->message = $message;
        $this->context = $context;
    }

    public function stage(): string
    {
        return $this->stage;
    }

    public function field(): string
    {
        return $this->field;
    }

    public function code(): string
    {
        return $this->code;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'stage' => $this->stage,
            'field' => $this->field,
            'code' => $this->code,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }
}
