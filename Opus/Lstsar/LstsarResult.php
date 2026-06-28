<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * Result emitted by the OPUS LSTSAR engine.
 */
final class LstsarResult
{
    private bool $ok;
    private ?string $recordId;

    /** @var array<string,mixed> */
    private array $record;

    /** @var list<LstsarViolation> */
    private array $violations;

    /**
     * @param array<string,mixed> $record
     * @param list<LstsarViolation> $violations
     */
    private function __construct(bool $ok, ?string $recordId, array $record, array $violations)
    {
        $this->ok = $ok;
        $this->recordId = $recordId;
        $this->record = $record;
        $this->violations = $violations;
    }

    /** @param array<string,mixed> $record */
    public static function stored(string $recordId, array $record): self
    {
        return new self(true, $recordId, $record, []);
    }

    /** @param list<LstsarViolation> $violations */
    public static function rejected(array $violations): self
    {
        return new self(false, null, [], $violations);
    }

    public function ok(): bool
    {
        return $this->ok;
    }

    public function recordId(): ?string
    {
        return $this->recordId;
    }

    /** @return array<string,mixed> */
    public function record(): array
    {
        return $this->record;
    }

    /** @return list<LstsarViolation> */
    public function violations(): array
    {
        return $this->violations;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'record_id' => $this->recordId,
            'record' => $this->record,
            'violations' => array_map(static fn (LstsarViolation $violation): array => $violation->toArray(), $this->violations),
        ];
    }
}
