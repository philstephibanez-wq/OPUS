<?php
declare(strict_types=1);

namespace Opus\Fsm;

/**
 * Contract interface for Opus\Fsm\FsmProcessor.
 *
 * @generated-by OPUS_P117M_R1_CONTRACTUALIZE_ALL
 *
 * Contract:
 * - OPUS framework component contract;
 * - explicit exception-awareness contract;
 * - profiler-awareness contract;
 * - complete self-documentation contract for RefBook output.
 */
interface FsmProcessorInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function name(): string;

    public function currentState(): string;

    public function reset(): void;

    /** @return array<string,mixed> */
    public function memory(): array;

    public function peek(string $name): mixed;

    public function poke(string $name, mixed $value): void;

    /** @return list<mixed> */
    public function stack(): array;

    public function push(mixed $value): void;

    public function pop(): mixed;

    public function setStackType(string $type): void;

    /** @return array<string,mixed> */
    public function snapshot(): array;

    /** @param array<string,mixed> $snapshot */
    public function restore(array $snapshot): void;
}
