<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * One explicit stage of the generic, model-driven LSTSAR flow.
 */
interface LstsarStageInterface
{
    public function name(): string;

    public function execute(LstsarContext $context): LstsarStageResult;
}
