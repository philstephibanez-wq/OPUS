<?php
declare(strict_types=1);

namespace Opus\MIDDLE\FSM;

final class FsmTransitionRegistry
{
    /** @var list<FsmTransition> */
    private array $transitions = [];

    public function add(FsmTransition $transition): void
    {
        $this->transitions[] = $transition;
    }

    /** @return list<FsmTransition> */
    public function all(): array
    {
        return $this->transitions;
    }
}
