<?php
declare(strict_types=1);

namespace Opus\Rcp\Fsm;

/** Strict finite-state machine for one REST/Composer execution lifecycle. */
final class RcpExecutionStateMachine implements RcpExecutionStateMachineInterface
{
    private string $current;
    /** @var list<string> */
    private array $history;

    /** @param array<string,list<string>> $transitions */
    public function __construct(
        string $initialState,
        private readonly array $transitions
    ) {
        if ($initialState === '' || !array_key_exists($initialState, $transitions)) {
            throw new \RuntimeException('OPUS_RCP_FSM_INITIAL_STATE_INVALID');
        }
        $this->current = $initialState;
        $this->history = [$initialState];
    }

    public function state(): string
    {
        return $this->current;
    }

    public function transition(string $target): void
    {
        $allowed = $this->transitions[$this->current] ?? [];
        if (!in_array($target, $allowed, true)) {
            throw new \RuntimeException(
                'OPUS_RCP_FSM_TRANSITION_INVALID:' . $this->current . ':' . $target
            );
        }
        $this->current = $target;
        $this->history[] = $target;
    }

    public function history(): array
    {
        return $this->history;
    }
}
