<?php
declare(strict_types=1);
namespace Opus\Event;
/*
 * OPUS_REFBOOK:
 *   domain: EVENT
 *   role: Class Event belongs to the EVENT Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the EVENT domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - event-overview
 *   diagrams:
 *     - event-runtime
 * END_OPUS_REFBOOK
 */
/**
 * Legacy-aligned Opus Event domain.
 * No silent fallback. Single responsibility only.
 * Since P112D4D_SAFE.
 */
final class Event
{
public function __construct(public readonly string $name, public readonly array $payload = []) { if (trim($this->name) === '') { throw new \InvalidArgumentException('OPUS_EVENT_NAME_EMPTY'); } }

}
