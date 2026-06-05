<?php

declare(strict_types=1);

namespace ASAP\SESSION;

/**
 * PUBLIC LEGACY-ALIGNED SESSION
 *
 * Role:
 *   Preserve the ASAP SESSION domain.
 *
 * Contract:
 *   No implicit session_start and no superglobal mutation.
 *
 * Since:
 *   P112D4D_SAFE
 *
 * Deepened:
 *   P112D4F
 */
final class Session
{
    /** @var array<string,mixed> */
    private array $values = [];

    /**
     * @param array<string,mixed> $values
     */
    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function get(string $key): mixed
    {
        if (!array_key_exists($key, $this->values)) {
            throw new \RuntimeException('ASAP_SESSION_KEY_MISSING: ' . $key);
        }

        return $this->values[$key];
    }

    public function getOrDefault(string $key, mixed $default): mixed
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->assertKey($key);
        $this->values[$key] = $value;
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
            throw new \InvalidArgumentException('ASAP_SESSION_KEY_EMPTY');
        }
    }
}
