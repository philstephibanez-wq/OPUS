<?php
declare(strict_types=1);

namespace Opus\Fsm;

/**
 * Contract for portable, file-backed FSM diagram layout persistence.
 *
 * Layout metadata is presentation only. It must never mutate FSM states,
 * signals, transitions, guards or actions.
 */
interface FsmDiagramLayoutStoreInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /**
     * @param array<string,mixed> $definition
     * @param array{
     *   positions:array<string,array{x:float,y:float,w:float,h:float,rank:int}>,
     *   width:float,
     *   height:float
     * } $automaticLayout
     * @return array<string,mixed>
     */
    public function resolve(array $definition, array $automaticLayout): array;

    /** @return array<string,mixed> */
    public function clientConfig(): array;
}
