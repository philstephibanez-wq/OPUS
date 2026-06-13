<?php

declare(strict_types=1);

namespace Opus\Database;

/*
 * OPUS_REFBOOK:
 *   domain: DATABASE
 *   role: Class DatabaseConnectionConfig belongs to the DATABASE Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the DATABASE domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - database-overview
 *   diagrams:
 *     - database-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC DATABASE CONNECTION CONFIGURATION
 *
 * Role:
 *   Carry one explicit site database configuration.
 *
 * Contract:
 *   Provider is mandatory.
 *   DSN may be explicit or built from provider fields by DatabaseDsnFactory.
 */
final class DatabaseConnectionConfig
{
    /**
     * @param array<string,string> $parameters
     * @param array<string,mixed> $pdoOptions
     */
    public function __construct(
        public readonly string $provider,
        public readonly ?string $dsn = null,
        public readonly ?string $user = null,
        public readonly ?string $password = null,
        public readonly array $parameters = [],
        public readonly array $pdoOptions = []
    ) {
        DatabaseProvider::assertSupported($this->provider);

        if ($this->dsn !== null && trim($this->dsn) === '') {
            throw DatabaseException::because('OPUS_DATABASE_DSN_EMPTY');
        }
    }

    public function normalizedProvider(): string
    {
        return DatabaseProvider::normalize($this->provider);
    }

    public function parameter(string $name): string
    {
        if (!array_key_exists($name, $this->parameters)) {
            throw DatabaseException::because('OPUS_DATABASE_PARAMETER_MISSING', $name);
        }

        $value = trim((string) $this->parameters[$name]);

        if ($value === '') {
            throw DatabaseException::because('OPUS_DATABASE_PARAMETER_EMPTY', $name);
        }

        return $value;
    }

    public function optionalParameter(string $name): ?string
    {
        if (!array_key_exists($name, $this->parameters)) {
            return null;
        }

        $value = trim((string) $this->parameters[$name]);

        return $value === '' ? null : $value;
    }
}
