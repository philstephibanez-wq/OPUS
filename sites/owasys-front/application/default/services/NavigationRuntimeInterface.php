<?php
declare(strict_types=1);

use Opus\Fsm\FsmSignalBus;

/** Owns the dedicated OWASYS Navigation EFSM runtime behind the navigation bus identity. */
interface OwasysNavigationRuntimeInterface
{
    public function synchronize(string $legacyState): string;

    public function register(FsmSignalBus $bus): void;

    public function currentState(): string;
}
