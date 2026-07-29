<?php
declare(strict_types=1);

namespace Opus\Fsm;

interface FsmSessionStoreInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function restore(FsmProcessor $processor): void;

    public function persist(FsmProcessor $processor): void;

    public function clear(): void;
}
