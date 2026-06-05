<?php
declare(strict_types=1);
namespace ASAP\ACTION;
/**
 * Legacy-aligned ASAP Action domain.
 * No silent fallback. Single responsibility only.
 * Since P112D4D_SAFE.
 */
final class Action
{
public function __construct(public readonly string $name) { if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this->name) !== 1) { throw new \InvalidArgumentException('ASAP_ACTION_NAME_INVALID'); } }

}
