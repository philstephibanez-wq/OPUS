<?php
declare(strict_types=1);
namespace Opus\Action;
/*
 * OPUS_REFBOOK:
 *   domain: ACTION
 *   role: Class Action belongs to the ACTION Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the ACTION domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - action-overview
 *   diagrams:
 *     - action-runtime
 * END_OPUS_REFBOOK
 */
/**
 * Legacy-aligned Opus Action domain.
 * No silent fallback. Single responsibility only.
 * Since P112D4D_SAFE.
 */
final class Action
{
public function __construct(public readonly string $name) { if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $this->name) !== 1) { throw new \InvalidArgumentException('OPUS_ACTION_NAME_INVALID'); } }

}
