<?php
declare(strict_types=1);

namespace Opus\Lstsar;

/**
 * Pure transform hook contract for destination assignments.
 *
 * Hooks are registered by name and must not perform hidden writes, raw SQL or DDL.
 */
interface LstsarTransformHookInterface
{
    public function name(): string;

    public function compute(LstsarTransformHookContext $context): mixed;
}
