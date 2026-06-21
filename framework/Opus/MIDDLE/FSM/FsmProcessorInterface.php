<?php
declare(strict_types=1);

namespace Opus\MIDDLE\FSM;

interface FsmProcessorInterface
{
    public function process(FsmSignal $signal): FsmResult;
}
