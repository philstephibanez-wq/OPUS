<?php
declare(strict_types=1);
namespace ASAP\SESSION;
/**
 * Legacy-aligned ASAP Session domain.
 * No silent fallback. Single responsibility only.
 * Since P112D4D_SAFE.
 */
final class Session
{

private array $values = [];
public function __construct(array $values = []) { $this->values = $values; }
public function get(string $key): mixed { if (!array_key_exists($key, $this->values)) { throw new \RuntimeException('ASAP_SESSION_KEY_MISSING: ' . $key); } return $this->values[$key]; }
public function set(string $key, mixed $value): void { if (trim($key) === '') { throw new \InvalidArgumentException('ASAP_SESSION_KEY_EMPTY'); } $this->values[$key] = $value; }
}
