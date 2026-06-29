<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * Immutable result returned by one LSTSAR stage.
 */
final class LstsarStageResult
{
    private string $stage;
    private bool $ok;
    /** @var array<string,mixed> */
    private array $payload;
    /** @var list<LstsarViolation> */
    private array $violations;
    /** @var list<array<string,mixed>> */
    private array $events;

    /**
     * @param array<string,mixed> $payload
     * @param list<LstsarViolation> $violations
     * @param list<array<string,mixed>> $events
     */
    private function __construct(string $stage, bool $ok, array $payload = [], array $violations = [], array $events = [])
    {
        foreach ($violations as $violation) {
            if (!$violation instanceof LstsarViolation) {
                throw new \InvalidArgumentException('OPUS_LSTSAR_STAGE_RESULT_VIOLATION_INVALID: ' . $stage);
            }
        }

        $this->stage = LstsarStageName::normalize($stage);
        $this->ok = $ok;
        $this->payload = $payload;
        $this->violations = array_values($violations);
        $this->events = array_values($events);
    }

    /** @param array<string,mixed> $payload @param list<array<string,mixed>> $events */
    public static function success(string $stage, array $payload = [], array $events = []): self
    {
        return new self($stage, true, $payload, [], $events);
    }

    /** @param list<LstsarViolation> $violations @param list<array<string,mixed>> $events */
    public static function rejected(string $stage, array $violations, array $events = []): self
    {
        return new self($stage, false, [], $violations, $events);
    }

    public function stage(): string
    {
        return $this->stage;
    }

    public function ok(): bool
    {
        return $this->ok;
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    /** @return list<LstsarViolation> */
    public function violations(): array
    {
        return $this->violations;
    }

    /** @return list<array<string,mixed>> */
    public function events(): array
    {
        return $this->events;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'stage' => $this->stage,
            'ok' => $this->ok,
            'payload' => $this->payload,
            'violations' => array_map(static fn (LstsarViolation $violation): array => $violation->toArray(), $this->violations),
            'events' => $this->events,
        ];
    }
}
