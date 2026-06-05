<?php

declare(strict_types=1);

namespace ASAP\Database;

/**
 * PUBLIC MULTI DATABASE CONNECTION CONFIGURATION
 *
 * Role:
 *   Carry named site database connections.
 *
 * Contract:
 *   - A site may declare several database connections.
 *   - Each connection name is explicit and unique.
 *   - No implicit fallback to another provider or connection.
 *   - The default connection, when requested, must be declared.
 */
final class DatabaseConnectionsConfig
{
    /**
     * @param array<string,DatabaseConnectionConfig> $connections
     */
    public function __construct(
        private readonly array $connections,
        private readonly ?string $defaultName = null
    ) {
        if ($this->connections === []) {
            throw DatabaseException::because('ASAP_DATABASE_CONNECTIONS_EMPTY');
        }

        foreach ($this->connections as $name => $config) {
            self::assertValidName((string) $name);

            if (!$config instanceof DatabaseConnectionConfig) {
                throw DatabaseException::because('ASAP_DATABASE_CONNECTION_CONFIG_INVALID', (string) $name);
            }
        }

        if ($this->defaultName !== null && !array_key_exists($this->defaultName, $this->connections)) {
            throw DatabaseException::because('ASAP_DATABASE_DEFAULT_CONNECTION_MISSING', $this->defaultName);
        }
    }

    public static function assertValidName(string $name): void
    {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]*$/', $name)) {
            throw DatabaseException::because('ASAP_DATABASE_CONNECTION_NAME_INVALID', $name);
        }
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_keys($this->connections));
    }

    public function count(): int
    {
        return count($this->connections);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->connections);
    }

    public function get(string $name): DatabaseConnectionConfig
    {
        if (!$this->has($name)) {
            throw DatabaseException::because('ASAP_DATABASE_CONNECTION_NOT_DECLARED', $name);
        }

        return $this->connections[$name];
    }

    public function defaultName(): string
    {
        if ($this->defaultName !== null) {
            return $this->defaultName;
        }

        $names = $this->names();

        if ($names === []) {
            throw DatabaseException::because('ASAP_DATABASE_CONNECTIONS_EMPTY');
        }

        return $names[0];
    }

    public function default(): DatabaseConnectionConfig
    {
        return $this->get($this->defaultName());
    }

    /**
     * @return array<string,DatabaseConnectionConfig>
     */
    public function all(): array
    {
        return $this->connections;
    }
}
