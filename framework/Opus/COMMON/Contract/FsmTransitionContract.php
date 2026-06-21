<?php
declare(strict_types=1);

namespace Opus\COMMON\Contract;

/**
 * Shared immutable description of an FSM transition.
 *
 * The transition contract is shared language. The FSM processor itself
 * remains in OPUS\MIDDLE\FSM.
 */
final class FsmTransitionContract
{
    public function __construct(
        private readonly string $signal,
        private readonly string $fromState,
        private readonly string $toState,
        private readonly string $action,
        private readonly string $ownerLayer
    ) {
    }

    public function signal(): string
    {
        return $this->signal;
    }

    public function fromState(): string
    {
        return $this->fromState;
    }

    public function toState(): string
    {
        return $this->toState;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function ownerLayer(): string
    {
        return $this->ownerLayer;
    }
}
