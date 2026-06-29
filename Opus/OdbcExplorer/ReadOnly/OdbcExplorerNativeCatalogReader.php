<?php
declare(strict_types=1);

namespace Opus\OdbcExplorer\ReadOnly;

use Opus\Database\Odbc\NativeOdbcConnection;
use Opus\Database\Odbc\OdbcDataSourceConfig;

/**
 * Native ODBC metadata reader for read-only catalog discovery.
 *
 * This class intentionally opens its own metadata connection so that the stable
 * OdbcConnectionInterface does not need to grow table-listing methods yet.
 */
final class OdbcExplorerNativeCatalogReader implements OdbcExplorerReadOnlyCatalogInterface
{
    private OdbcDataSourceConfig $config;
    /** @var list<string> */
    private array $types;
    /** @var mixed */
    private $connection = null;

    /** @param list<string> $types */
    public function __construct(OdbcDataSourceConfig $config, array $types = ['TABLE', 'VIEW'])
    {
        $this->config = $config;
        $this->types = array_values(array_filter(array_map(
            static fn (string $type): string => strtoupper(trim($type)),
            $types
        )));
        if ($this->types === []) {
            $this->types = ['TABLE', 'VIEW'];
        }
    }

    public function __destruct()
    {
        if ($this->connection !== null) {
            @odbc_close($this->connection);
        }
    }

    public function dataSourceId(): string
    {
        return $this->config->id();
    }

    /** @return list<OdbcExplorerTableReference> */
    public function listTables(): array
    {
        $this->connect();

        $result = @odbc_tables($this->connection, null, null, null, null);
        if ($result === false) {
            throw new \RuntimeException('OPUS_ODBC_EXPLORER_LIST_TABLES_FAILED: ' . $this->config->id() . ': ' . (string) @odbc_errormsg($this->connection));
        }

        $tables = [];
        while (($row = @odbc_fetch_array($result)) !== false) {
            $name = (string) $this->rowValue($row, ['TABLE_NAME', 'table_name'], '');
            if (trim($name) === '') {
                continue;
            }

            $type = strtoupper((string) $this->rowValue($row, ['TABLE_TYPE', 'table_type'], 'TABLE'));
            if (!in_array($type, $this->types, true)) {
                continue;
            }

            $tables[] = new OdbcExplorerTableReference(
                $name,
                $type,
                $this->stringOrNull($this->rowValue($row, ['TABLE_CAT', 'table_cat'], null)),
                $this->stringOrNull($this->rowValue($row, ['TABLE_SCHEM', 'table_schem'], null)),
                $this->stringOrNull($this->rowValue($row, ['REMARKS', 'remarks'], null))
            );
        }

        usort($tables, static function (OdbcExplorerTableReference $a, OdbcExplorerTableReference $b): int {
            return strcmp($a->qualifiedName(), $b->qualifiedName());
        });

        return array_values($tables);
    }

    private function connect(): void
    {
        if ($this->connection !== null) {
            return;
        }

        NativeOdbcConnection::assertExtensionAvailable();
        $connection = @odbc_connect(
            $this->config->connectionTarget(),
            $this->config->username() ?? '',
            $this->config->password() ?? ''
        );

        if ($connection === false) {
            throw new \RuntimeException('OPUS_ODBC_EXPLORER_CONNECT_FAILED: ' . $this->config->id() . ': ' . (string) @odbc_errormsg());
        }

        $this->connection = $connection;
    }

    /** @param array<string,mixed> $row */
    private function rowValue(array $row, array $keys, mixed $default): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return $default;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
