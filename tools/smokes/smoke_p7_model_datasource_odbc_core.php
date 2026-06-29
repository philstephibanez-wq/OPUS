<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use Opus\Database\Odbc\NativeOdbcConnection;
use Opus\Database\Odbc\OdbcColumn;
use Opus\Database\Odbc\OdbcConnectionInterface;
use Opus\Database\Odbc\OdbcDataSourceConfig;
use Opus\Database\Odbc\OdbcTableInspector;
use Opus\Model\Adapter\OdbcModelAdapter;
use Opus\Model\ModelRecord;
use Opus\Model\TableModel;

function p7_model_odbc_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class P7ModelDatasourceOdbcFakeConnection implements OdbcConnectionInterface
{
    private OdbcDataSourceConfig $config;
    /** @var list<OdbcColumn> */
    private array $columns;
    /** @var list<array<string,mixed>> */
    private array $rows;
    /** @var list<array<string,mixed>> */
    public array $inserted = [];
    private bool $connected = false;

    /**
     * @param list<OdbcColumn> $columns
     * @param list<array<string,mixed>> $rows
     */
    public function __construct(OdbcDataSourceConfig $config, array $columns, array $rows)
    {
        $this->config = $config;
        $this->columns = $columns;
        $this->rows = $rows;
    }

    public function dataSource(): OdbcDataSourceConfig
    {
        return $this->config;
    }

    public function connect(): void
    {
        $this->connected = true;
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function listColumns(string $table): array
    {
        p7_model_odbc_assert($table === 'source_clients', 'CHECK_FAKE_ODBC_TABLE_NAME_FAILED');

        return $this->columns;
    }

    public function fetchTable(string $table, int $limit = 0): array
    {
        p7_model_odbc_assert($table === 'source_clients', 'CHECK_FAKE_ODBC_FETCH_TABLE_FAILED');

        return $limit > 0 ? array_slice($this->rows, 0, $limit) : $this->rows;
    }

    public function insertRow(string $table, array $row): int
    {
        p7_model_odbc_assert($table === 'source_clients', 'CHECK_FAKE_ODBC_INSERT_TABLE_FAILED');
        $this->inserted[] = $row;

        return 1;
    }
}

$root = dirname(__DIR__, 2);

echo "P7_MODEL_DATASOURCE_ODBC_CORE_SMOKE\n";

p7_model_odbc_assert(extension_loaded('odbc'), 'CHECK_PHP_ODBC_EXTENSION_MISSING');
NativeOdbcConnection::assertExtensionAvailable();
echo "CHECK_PHP_ODBC_EXTENSION=OK\n";

$dsnConfig = OdbcDataSourceConfig::fromArray([
    'id' => 'demo_dsn',
    'driver' => 'odbc',
    'dsn' => 'DEMO_DSN',
]);
p7_model_odbc_assert($dsnConfig->driver() === 'odbc', 'CHECK_ODBC_CONFIG_DRIVER_FAILED');
p7_model_odbc_assert($dsnConfig->connectionTarget() === 'DEMO_DSN', 'CHECK_ODBC_CONFIG_DSN_FAILED');

$dsnLessConfig = OdbcDataSourceConfig::fromArray([
    'id' => 'demo_dsn_less',
    'driver' => 'odbc',
    'connection_string' => 'Driver={SQL Server};Server=.;Database=demo;',
]);
p7_model_odbc_assert(str_contains($dsnLessConfig->connectionTarget(), 'Driver='), 'CHECK_ODBC_CONFIG_DSN_LESS_FAILED');
echo "CHECK_ODBC_CONFIG=OK\n";

$connection = new P7ModelDatasourceOdbcFakeConnection(
    $dsnConfig,
    [
        new OdbcColumn('id', 'INTEGER', null, 10, 0, false, 1),
        new OdbcColumn('code', 'VARCHAR', null, 4, null, false, 2),
        new OdbcColumn('label', 'VARCHAR', null, 40, null, true, 3),
        new OdbcColumn('amount', 'DECIMAL', null, 12, 2, true, 4),
    ],
    [
        ['id' => '1', 'code' => 'C001', 'label' => 'Alpha', 'amount' => '12.50'],
        ['id' => '2', 'code' => 'C002', 'label' => 'Beta', 'amount' => '25.00'],
    ]
);

$inspector = new OdbcTableInspector($connection);
$model = $inspector->inspectTable('model.clients.source', 'source_clients');
p7_model_odbc_assert($model instanceof TableModel, 'CHECK_TABLE_MODEL_TYPE_FAILED');
p7_model_odbc_assert($model->id() === 'model.clients.source', 'CHECK_TABLE_MODEL_ID_FAILED');
p7_model_odbc_assert($model->field('id') !== null && $model->field('id')->type() === 'integer', 'CHECK_TABLE_MODEL_ID_FIELD_FAILED');
p7_model_odbc_assert($model->field('code') !== null && $model->field('code')->length() === 4, 'CHECK_TABLE_MODEL_CODE_LENGTH_FAILED');
p7_model_odbc_assert($model->field('amount') !== null && $model->field('amount')->type() === 'decimal', 'CHECK_TABLE_MODEL_AMOUNT_TYPE_FAILED');
echo "CHECK_ODBC_TABLE_TO_MODEL=OK\n";

$adapter = new OdbcModelAdapter($connection);
$records = $adapter->readRecords($model);
p7_model_odbc_assert(count($records) === 2, 'CHECK_MODEL_RECORD_COUNT_FAILED');
p7_model_odbc_assert($records[0] instanceof ModelRecord, 'CHECK_MODEL_RECORD_TYPE_FAILED');
p7_model_odbc_assert($records[0]->value('code') === 'C001', 'CHECK_MODEL_RECORD_VALUE_FAILED');
echo "CHECK_ODBC_ROWS_TO_MODEL_RECORDS=OK\n";

$written = $adapter->writeRecords($model, [$records[0], ['id' => '3', 'code' => 'C003', 'label' => 'Gamma', 'amount' => '75.25']]);
p7_model_odbc_assert($written === 2, 'CHECK_MODEL_RECORD_WRITE_COUNT_FAILED');
p7_model_odbc_assert(count($connection->inserted) === 2, 'CHECK_MODEL_RECORD_WRITE_CAPTURE_FAILED');
echo "CHECK_MODEL_RECORDS_TO_ODBC=OK\n";

$forbiddenFiles = [
    $root . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Odbc' . DIRECTORY_SEPARATOR . 'OdbcDataSourceConfig.php',
    $root . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Odbc' . DIRECTORY_SEPARATOR . 'NativeOdbcConnection.php',
    $root . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Model' . DIRECTORY_SEPARATOR . 'Adapter' . DIRECTORY_SEPARATOR . 'OdbcModelAdapter.php',
];
foreach ($forbiddenFiles as $file) {
    $text = (string) file_get_contents($file);
    if (preg_match('/new\s+PDO|mysqli_|mysql_connect|pg_connect|sqlite_open|SQLite3\s*\(/i', $text) === 1) {
        throw new RuntimeException('CHECK_ODBC_ONLY_FORBIDDEN_DIRECT_DATABASE_CALL: ' . $file);
    }
}
echo "CHECK_ODBC_ONLY_MODEL_DATABASE=OK\n";

echo "P7_MODEL_DATASOURCE_ODBC_CORE_SMOKE_OK\n";
