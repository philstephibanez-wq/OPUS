<?php
declare(strict_types=1);

namespace Opus\MIDDLE\FSM;

final class FsmSignal
{
    public function __construct(
        private readonly string $name,
        private readonly string $sourceLayer,
        private readonly string $targetLayer
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function sourceLayer(): string
    {
        return $this->sourceLayer;
    }

    public function targetLayer(): string
    {
        return $this->targetLayer;
    }
}
