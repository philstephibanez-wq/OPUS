<?php

declare(strict_types=1);

namespace Opus\Database;

/*
 * OPUS_REFBOOK:
 *   domain: DATABASE
 *   role: Class DatabaseDsnFactory belongs to the DATABASE Opus framework domain.
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
 * PUBLIC DATABASE DSN FACTORY
 *
 * Role:
 *   Build PDO DSNs from explicit provider configuration.
 *
 * Contract:
 *   No default host, database, service name or file path.
 */
final class DatabaseDsnFactory
{
    public function build(DatabaseConnectionConfig $config): string
    {
        if ($config->dsn !== null) {
            return $config->dsn;
        }

        return match ($config->normalizedProvider()) {
            DatabaseProvider::MYSQL, DatabaseProvider::MARIADB => $this->mysql($config),
            DatabaseProvider::POSTGRESQL => $this->postgresql($config),
            DatabaseProvider::SQLITE => $this->sqlite($config),
            DatabaseProvider::ORACLE => $this->oracle($config),
            DatabaseProvider::ODBC => $this->odbc($config),
            DatabaseProvider::SQLSERVER => $this->sqlserver($config),
            default => throw DatabaseException::because('OPUS_DATABASE_PROVIDER_UNSUPPORTED', $config->provider),
        };
    }

    private function mysql(DatabaseConnectionConfig $config): string
    {
        $host = $config->parameter('host');
        $database = $config->parameter('database');
        $port = $config->optionalParameter('port');
        $charset = $config->optionalParameter('charset') ?? 'utf8mb4';

        $dsn = 'mysql:host=' . $host;

        if ($port !== null) {
            $dsn .= ';port=' . $port;
        }

        return $dsn . ';dbname=' . $database . ';charset=' . $charset;
    }

    private function postgresql(DatabaseConnectionConfig $config): string
    {
        $host = $config->parameter('host');
        $database = $config->parameter('database');
        $port = $config->optionalParameter('port');

        $dsn = 'pgsql:host=' . $host;

        if ($port !== null) {
            $dsn .= ';port=' . $port;
        }

        return $dsn . ';dbname=' . $database;
    }

    private function sqlite(DatabaseConnectionConfig $config): string
    {
        $path = $config->parameter('path');

        return 'sqlite:' . $path;
    }

    private function oracle(DatabaseConnectionConfig $config): string
    {
        $host = $config->parameter('host');
        $service = $config->parameter('service');
        $port = $config->optionalParameter('port') ?? '1521';
        $charset = $config->optionalParameter('charset') ?? 'AL32UTF8';

        return 'oci:dbname=//' . $host . ':' . $port . '/' . $service . ';charset=' . $charset;
    }

    private function odbc(DatabaseConnectionConfig $config): string
    {
        $name = $config->parameter('name');

        return 'odbc:' . $name;
    }

    private function sqlserver(DatabaseConnectionConfig $config): string
    {
        $host = $config->parameter('host');
        $database = $config->parameter('database');
        $port = $config->optionalParameter('port');

        $server = $host;

        if ($port !== null) {
            $server .= ',' . $port;
        }

        return 'sqlsrv:Server=' . $server . ';Database=' . $database;
    }
}
