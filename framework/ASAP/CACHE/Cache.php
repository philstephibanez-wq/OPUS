<?php
declare(strict_types=1);
namespace ASAP\CACHE;
/**
 * Legacy-aligned ASAP Cache domain.
 * No silent fallback. Single responsibility only.
 * Since P112D4D_SAFE.
 */
final class Cache
{

private array $values = [];
public function set(string $key, mixed $value): void { if (trim($key) === '') { throw new \InvalidArgumentException('ASAP_CACHE_KEY_EMPTY'); } $this->values[$key] = $value; }
public function get(string $key): mixed { if (!array_key_exists($key, $this->values)) { throw new \RuntimeException('ASAP_CACHE_KEY_MISSING: ' . $key); } return $this->values[$key]; }
public function has(string $key): bool { return array_key_exists($key, $this->values); }
}
