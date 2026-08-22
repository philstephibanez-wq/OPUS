<?php
declare(strict_types=1);

use Opus\Fsm\FsmActionDispatcher;

/** Builds the action dispatcher from developer-programmed application PHP. */
final class OwasysFsmActionHandlers
{
    public function __construct(
        private readonly OwasysAuthSession $session,
        private readonly OwasysRuntimeSecurity $security,
        private readonly ?OwasysRegistryModel $registry
    ) {
    }

    /** @return list<string> */
    public function handlerNames(): array
    {
        return array_keys($this->handlers());
    }

    public function dispatcher(): FsmActionDispatcher
    {
        return new FsmActionDispatcher($this->handlers());
    }

    /** @return array<string,callable> */
    private function handlers(): array
    {
        return (new OwasysFsmDeveloperHandlers(
            $this->security,
            $this->session,
            $this->registry
        ))->actions();
    }
}