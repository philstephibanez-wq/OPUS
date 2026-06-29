<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use Opus\Database\Odbc\OdbcColumn;
use Opus\Database\Odbc\OdbcConnectionInterface;
use Opus\Database\Odbc\OdbcDataSourceConfig;
use Opus\OdbcExplorer\OdbcExplorerCapability;
use Opus\OdbcExplorer\OdbcExplorerContract;
use Opus\OdbcExplorer\OdbcExplorerDataSourceRegistry;
use Opus\OdbcExplorer\OdbcExplorerFeature;
use Opus\OdbcExplorer\OdbcExplorerService;

echo "P7_ODBC_EXPLORER_CONTRACT_CORE_SMOKE\n";

final class SmokeP7ExplorerConnection implements OdbcConnectionInterface
{
    private OdbcDataSourceConfig $config;
    private bool $connected = false;
    /** @var list<array<string,mixed>> */
    private array $written = [];

    public function __construct()
    {
        $this->config = OdbcDataSourceConfig::fromArray([
            'id' => 'smoke_odbc',
            'driver' => 'odbc',
            'dsn' => 'SMOKE_DSN',
        ]);
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
        if ($table !== 'clients') {
            throw new RuntimeException('SMOKE_TABLE_UNEXPECTED: ' . $table);
        }

        return [
            new OdbcColumn('id', 'INTEGER', null, 10, 0, false, 1),
            new OdbcColumn('code', 'VARCHAR', null, 8, null, false, 2),
            new OdbcColumn('name', 'VARCHAR', null, 80, null, true, 3),
        ];
    }

    public function fetchTable(string $table, int $limit = 0): array
    {
        $rows = [
            ['id' => 1, 'code' => 'A001', 'name' => 'Alice'],
            ['id' => 2, 'code' => 'B002', 'name' => 'Bob'],
        ];

        return $limit > 0 ? array_slice($rows, 0, $limit) : $rows;
    }

    public function insertRow(string $table, array $row): int
    {
        $this->written[] = $row;
        return 1;
    }
}

function smoke_check(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException($label . '=FAIL');
    }

    echo $label . "=OK\n";
}

$contract = new OdbcExplorerContract();
$contractArray = $contract->toArray();
$features = array_column($contractArray['capabilities'], 'feature');

smoke_check($contractArray['contract'] === OdbcExplorerContract::CONTRACT_ID, 'CHECK_ODBC_EXPLORER_CONTRACT_ID');
smoke_check(in_array(OdbcExplorerFeature::GENERATE_TABLE_MODEL, $features, true), 'CHECK_ODBC_EXPLORER_MODEL_FEATURE');
smoke_check(in_array(OdbcExplorerFeature::GENERATE_LSTSAR_DRAFT, $features, true), 'CHECK_ODBC_EXPLORER_LSTSAR_FEATURE');
smoke_check($contract->capability(OdbcExplorerFeature::DELETE_ROW)?->status() === OdbcExplorerCapability::STATUS_GUARDED, 'CHECK_ODBC_EXPLORER_DESTRUCTIVE_GUARDED');

$registry = OdbcExplorerDataSourceRegistry::fromArray([
    [
        'id' => 'smoke_odbc',
        'driver' => 'odbc',
        'dsn' => 'SMOKE_DSN',
    ],
]);

smoke_check($registry->ids() === ['smoke_odbc'], 'CHECK_ODBC_EXPLORER_DATASOURCE_REGISTRY');

$service = new OdbcExplorerService(new SmokeP7ExplorerConnection());
$connection = $service->testConnection();
smoke_check($connection['ok'] === true, 'CHECK_ODBC_EXPLORER_CONNECTION_TEST');

$model = $service->generateTableModel('client_model', 'clients');
smoke_check($model->tableName() === 'clients' && $model->field('code') !== null, 'CHECK_ODBC_EXPLORER_TABLE_MODEL');

$preview = $service->previewRows($model, 1);
smoke_check(count($preview) === 1 && $preview[0]['code'] === 'A001', 'CHECK_ODBC_EXPLORER_PREVIEW_ROWS');

$draft = $service->prepareLstsarDraft($model);
smoke_check($draft['contract'] === 'OPUS_LSTSAR_ODBC_MODEL_DRAFT_V1', 'CHECK_ODBC_EXPLORER_LSTSAR_DRAFT');
smoke_check(($draft['mapping']['code'] ?? null) === 'code', 'CHECK_ODBC_EXPLORER_LSTSAR_MAPPING');

$forbidden = [];
$paths = [
    $root . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'OdbcExplorer',
];
foreach ($paths as $path) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents((string) $file);
        if (!is_string($contents)) {
            continue;
        }
        if (preg_match('/\b(PDO|mysqli_|mysql_|SQLite3|sqlite_|pgsql_)\b/i', $contents) === 1) {
            $forbidden[] = (string) $file;
        }
    }
}
smoke_check($forbidden === [], 'CHECK_ODBC_EXPLORER_ODBC_ONLY');

echo "P7_ODBC_EXPLORER_CONTRACT_CORE_SMOKE_OK\n";
