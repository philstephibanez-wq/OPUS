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

    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $renderedGeometry
     */
    public function persistRenderedGeometry(
        array $definition,
        array $renderedGeometry
    ): void;

    /**
     * Prepare an optimistic layout-file rewrite for a semantic state rename.
     * No write is performed by this method.
     *
     * @param array<string,mixed> $oldDefinition
     * @param array<string,mixed> $newDefinition
     * @return array{
     *   path:string,
     *   expected_sha256:string,
     *   content:string,
     *   state_position_migrated:bool,
     *   marker_count_migrated:int
     * }|null
     */
    public function prepareStateIdentityRefactor(
        array $oldDefinition,
        array $newDefinition,
        string $oldStateId,
        string $newStateId,
        string $newDefinitionSha256
    ): ?array;

    /** @return array<string,mixed> */
    public function clientConfig(): array;
}
