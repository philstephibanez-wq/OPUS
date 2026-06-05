<?php

declare(strict_types=1);

namespace ASAP\CACHE;

/**
 * PUBLIC LEGACY-ALIGNED CACHE
 *
 * Role:
 *   Preserve the ASAP CACHE domain with explicit in-memory cache semantics.
 *
 * Contract:
 *   No filesystem fallback. Missing keys fail explicitly.
 *
 * Since:
 *   P112D4D_SAFE
 *
 * Deepened:
 *   P112D4F
 */
final class Cache
{
    /** @var array<string,mixed> */
    private array $values = [];

    public function set(string $key, mixed $value): void
    {
        $this->assertKey($key);
        $this->values[$key] = $value;
    }

    public function get(string $key): mixed
    {
        if (!array_key_exists($key, $this->values)) {
            throw new \RuntimeException('ASAP_CACHE_KEY_MISSING: ' . $key);
        }

        return $this->values[$key];
    }

    public function getOrDefault(string $key, mixed $default): mixed
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    public function clear(): void
    {
        $this->values = [];
    }

    public function count(): int
    {
        return count($this->values);
    }

    /**
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    private function assertKey(string $key): void
    {
        if (trim($key) === '') {
            throw new \InvalidArgumentException('ASAP_CACHE_KEY_EMPTY');
        }
    }
}
