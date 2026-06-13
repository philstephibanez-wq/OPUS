<?php

declare(strict_types=1);

namespace Opus\Config;

/*
 * OPUS_REFBOOK:
 *   domain: CONFIG
 *   role: Class ConfigBag belongs to the CONFIG Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the CONFIG domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - config-overview
 *   diagrams:
 *     - config-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Carry typed configuration values.
 *
 * Responsibility:
 *   Provide strict getters without silent defaults.
 *
 * Contract:
 *   Missing configuration keys fail explicitly.
 *
 * Since:
 *   P112D4A
 */
final class ConfigBag
{
    /**
     * @param array<string,mixed> $values Configuration values.
     */
    public function __construct(private readonly array $values)
    {
    }

    public function string(string $key): string
    {
        $value = $this->require($key);

        if (!is_string($value)) {
            throw ConfigException::because('OPUS_CONFIG_VALUE_NOT_STRING', $key);
        }

        return $value;
    }

    public function integer(string $key): int
    {
        $value = $this->require($key);

        if (!is_int($value)) {
            throw ConfigException::because('OPUS_CONFIG_VALUE_NOT_INT', $key);
        }

        return $value;
    }

    public function boolean(string $key): bool
    {
        $value = $this->require($key);

        if (!is_bool($value)) {
            throw ConfigException::because('OPUS_CONFIG_VALUE_NOT_BOOL', $key);
        }

        return $value;
    }

    /**
     * @return mixed
     */
    private function require(string $key): mixed
    {
        if (!array_key_exists($key, $this->values)) {
            throw ConfigException::because('OPUS_CONFIG_KEY_MISSING', $key);
        }

        return $this->values[$key];
    }
}
