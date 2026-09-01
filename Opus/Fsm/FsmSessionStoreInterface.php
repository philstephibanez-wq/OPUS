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

    /**
     * Restores a compatible persisted snapshot.
     *
     * Returns false when no snapshot exists or when the only incompatibility
     * is a state removed from the current FSM definition. In the latter case,
     * the stale snapshot is discarded and the processor is reset.
     */
    public function restoreCompatible(FsmProcessor $processor): bool;

    public function persist(FsmProcessor $processor): void;

    public function clear(): void;
}
