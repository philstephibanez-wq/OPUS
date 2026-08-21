<?php
declare(strict_types=1);

namespace Opus\Fsm\Definition;

interface FsmDefinitionEditorInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /**
     * @param array<string,mixed> $definition
     * @param array<string,mixed> $command
     * @return array{definition:array<string,mixed>,diagnostics:list<array{code:string,path:string,message:string}>,operation:string,refactor:array<string,string>}
     */
    public function apply(array $definition, array $command): array;
}