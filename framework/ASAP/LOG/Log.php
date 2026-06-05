<?php
declare(strict_types=1);
namespace ASAP\LOG;
/**
 * Legacy-aligned ASAP Log domain.
 * No silent fallback. Single responsibility only.
 * Since P112D4D_SAFE.
 */
final class Log
{

private array $entries = [];
public function add(string $message): void { if (trim($message) === '') { throw new \InvalidArgumentException('ASAP_LOG_MESSAGE_EMPTY'); } $this->entries[] = $message; }
public function entries(): array { return $this->entries; }
}
