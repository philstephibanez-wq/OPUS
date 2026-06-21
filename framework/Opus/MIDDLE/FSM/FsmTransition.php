<?php
declare(strict_types=1);

namespace Opus\MIDDLE\FSM;

final class FsmTransition
{
    public function __construct(
        public readonly string $signal,
        public readonly string $fromState,
        public readonly string $toState,
        public readonly string $action
    ) {
    }
}
