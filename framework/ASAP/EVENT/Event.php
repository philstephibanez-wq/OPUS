<?php
declare(strict_types=1);
namespace ASAP\EVENT;
/**
 * Legacy-aligned ASAP Event domain.
 * No silent fallback. Single responsibility only.
 * Since P112D4D_SAFE.
 */
final class Event
{
public function __construct(public readonly string $name, public readonly array $payload = []) { if (trim($this->name) === '') { throw new \InvalidArgumentException('ASAP_EVENT_NAME_EMPTY'); } }

}
