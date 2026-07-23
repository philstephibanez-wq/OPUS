<?php
declare(strict_types=1);

namespace Opus\Rcp\Fsm;

interface RcpExecutionStateMachineInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function state(): string;
    public function transition(string $target): void;
    /** @return list<string> */
    public function history(): array;
}
