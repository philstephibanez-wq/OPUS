<?php

declare(strict_types=1);

namespace ASAP;

/**
 * PUBLIC LEGACY-ALIGNED CONFIGURATION
 *
 * Role:
 *   Preserve the original ASAP Configuration object as typed key/value config.
 *
 * Responsibility:
 *   Store and expose declared configuration values.
 *
 * Contract:
 *   Missing keys fail explicitly unless caller checks `has()` first.
 *
 * Since:
 *   P112D4C
 */
final class Configuration
{
    /** @var array<string,mixed> */
    private array $values = [];

    /**
     * @param array<string,mixed> $values Initial configuration.
     */
    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            throw Exception::because('ASAP_CONFIGURATION_KEY_MISSING', $key);
        }

        return $this->values[$key];
    }

    public function set(string $key, mixed $value): void
    {
        if (trim($key) === '') {
            throw Exception::because('ASAP_CONFIGURATION_KEY_EMPTY');
        }

        $this->values[$key] = $value;
    }

    /**
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return $this->values;
    }
}
