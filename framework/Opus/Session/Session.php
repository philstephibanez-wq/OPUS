<?php

declare(strict_types=1);

namespace Opus\Session;

/*
 * OPUS_REFBOOK:
 *   domain: SESSION
 *   role: Class Session belongs to the SESSION Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the SESSION domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - session-overview
 *   diagrams:
 *     - session-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY-ALIGNED SESSION
 *
 * Role:
 *   Preserve the Opus SESSION domain.
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
            throw new \RuntimeException('OPUS_SESSION_KEY_MISSING: ' . $key);
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
            throw new \InvalidArgumentException('OPUS_SESSION_KEY_EMPTY');
        }
    }
}
