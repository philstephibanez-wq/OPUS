<?php
declare(strict_types=1);

namespace Opus\Database\Odbc;

/**
 * Official OPUS database access boundary.
 *
 * Any class that needs a database must depend on this ODBC boundary or on a
 * higher-level OPUS Model adapter built on top of it.
 */
interface OdbcConnectionInterface
{
    public function dataSource(): OdbcDataSourceConfig;

    public function connect(): void;

    public function disconnect(): void;

    public function isConnected(): bool;

    /** @return list<OdbcColumn> */
    public function listColumns(string $table): array;

    /** @return list<array<string,mixed>> */
    public function fetchTable(string $table, int $limit = 0): array;

    /** @param array<string,mixed> $row */
    public function insertRow(string $table, array $row): int;
}
