<?php
declare(strict_types=1);

namespace Opus\Fsm\Definition;

interface FsmDefinitionValidatorInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    /** @param array<string,mixed> $definition @return array{valid:bool,diagnostics:list<array{code:string,path:string,message:string}>} */
    public function validate(array $definition): array;

    /** @param array<string,mixed> $definition */
    public function assertValid(array $definition): void;
}