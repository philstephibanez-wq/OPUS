<?php
declare(strict_types=1);

namespace Opus\Database\Odbc;

/**
 * Immutable OPUS ODBC data-source declaration.
 *
 * OPUS database access is intentionally ODBC-only: concrete engines such as
 * MySQL, SQL Server, Access, PostgreSQL or SQLite must be reached through an
 * ODBC driver or DSN and must not leak into Model or LSTSAR classes.
 */
final class OdbcDataSourceConfig
{
    private string $id;
    private ?string $dsn;
    private ?string $connectionString;
    private ?string $username;
    private ?string $password;
    /** @var array<string,mixed> */
    private array $options;

    /**
     * @param array<string,mixed> $options
     */
    private function __construct(string $id, ?string $dsn, ?string $connectionString, ?string $username, ?string $password, array $options = [])
    {
        $id = trim($id);
        if (!preg_match('/^[a-zA-Z0-9_\-.]{1,80}$/', $id)) {
            throw new \InvalidArgumentException('OPUS_ODBC_DATASOURCE_ID_INVALID: ' . $id);
        }

        $dsn = $dsn !== null ? trim($dsn) : null;
        $connectionString = $connectionString !== null ? trim($connectionString) : null;

        if (($dsn === null || $dsn === '') && ($connectionString === null || $connectionString === '')) {
            throw new \InvalidArgumentException('OPUS_ODBC_DATASOURCE_TARGET_MISSING: ' . $id);
        }

        $this->id = $id;
        $this->dsn = $dsn !== '' ? $dsn : null;
        $this->connectionString = $connectionString !== '' ? $connectionString : null;
        $this->username = $username !== null && trim($username) !== '' ? $username : null;
        $this->password = $password !== null ? $password : null;
        $this->options = $options;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $driver = strtolower(trim((string) ($data['driver'] ?? 'odbc')));
        if ($driver !== 'odbc') {
            throw new \InvalidArgumentException('OPUS_ODBC_DATASOURCE_DRIVER_FORBIDDEN: ' . $driver);
        }

        return new self(
            (string) ($data['id'] ?? ''),
            isset($data['dsn']) ? (string) $data['dsn'] : null,
            isset($data['connection_string']) ? (string) $data['connection_string'] : null,
            isset($data['username']) ? (string) $data['username'] : null,
            isset($data['password']) ? (string) $data['password'] : null,
            isset($data['options']) && is_array($data['options']) ? $data['options'] : []
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function driver(): string
    {
        return 'odbc';
    }

    public function dsn(): ?string
    {
        return $this->dsn;
    }

    public function connectionString(): ?string
    {
        return $this->connectionString;
    }

    public function connectionTarget(): string
    {
        return $this->connectionString ?? (string) $this->dsn;
    }

    public function username(): ?string
    {
        return $this->username;
    }

    public function password(): ?string
    {
        return $this->password;
    }

    /** @return array<string,mixed> */
    public function options(): array
    {
        return $this->options;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'driver' => 'odbc',
            'dsn' => $this->dsn,
            'connection_string' => $this->connectionString,
            'username' => $this->username,
            'options' => $this->options,
        ];
    }
}
