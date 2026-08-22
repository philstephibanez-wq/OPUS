<?php
declare(strict_types=1);

namespace Opus\Fsm\Definition;

/**
 * Source-level editor for developer-programmed EFSM guard/action callables.
 *
 * The editor never evaluates developer source. It edits bounded managed
 * regions and validates the resulting PHP with the native tokenizer parser.
 */
interface FsmHandlerSourceEditorInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /**
     * @return array{
     *   guard:list<array{id:string,code:string,sha256:string}>,
     *   action:list<array{id:string,code:string,sha256:string}>
     * }
     */
    public function catalog(string $source): array;

    /**
     * @return array{
     *   source:string,
     *   kind:string,
     *   id:string,
     *   mode:string,
     *   created:bool,
     *   handler_sha256:string
     * }
     */
    public function upsert(
        string $source,
        string $kind,
        string $id,
        string $code,
        string $mode
    ): array;
}