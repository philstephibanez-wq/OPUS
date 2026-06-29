<?php
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Opus\Database\Odbc\OdbcColumn;
use Opus\Database\Odbc\OdbcConnectionInterface;
use Opus\Database\Odbc\OdbcDataSourceConfig;
use Opus\OdbcExplorer\OdbcExplorerService;
use Opus\OdbcExplorer\ReadOnly\OdbcExplorerReadOnlyCatalogInterface;
use Opus\OdbcExplorer\ReadOnly\OdbcExplorerReadOnlyService;
use Opus\OdbcExplorer\ReadOnly\OdbcExplorerTableReference;

final class P7ReadOnlyFakeConnection implements OdbcConnectionInterface
{
    private bool $connected = false;
    private OdbcDataSourceConfig $config;

    public function __construct()
    {
        $this->config = OdbcDataSourceConfig::fromArray([
            'id' => 'demo_odbc',
            'driver' => 'odbc',
            'dsn' => 'DEMO_DSN',
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
        if ($table !== 'users' && $table !== 'public.users') {
            throw new RuntimeException('UNEXPECTED_TABLE_COLUMNS: ' . $table);
        }

        return [
            new OdbcColumn('id', 'INTEGER', 4, 11, null, false, 1),
            new OdbcColumn('email', 'VARCHAR', 12, 255, null, false, 2),
            new OdbcColumn('display_name', 'VARCHAR', 12, 120, null, true, 3),
        ];
    }

    public function fetchTable(string $table, int $limit = 0): array
    {
        if ($table !== 'users' && $table !== 'public.users') {
            throw new RuntimeException('UNEXPECTED_TABLE_ROWS: ' . $table);
        }

        $rows = [
            ['id' => 1, 'email' => 'alpha@example.test', 'display_name' => 'Alpha'],
            ['id' => 2, 'email' => 'beta@example.test', 'display_name' => 'Beta'],
            ['id' => 3, 'email' => 'gamma@example.test', 'display_name' => 'Gamma'],
        ];

        return $limit > 0 ? array_slice($rows, 0, $limit) : $rows;
    }

    public function insertRow(string $table, array $row): int
    {
        throw new RuntimeException('READONLY_FAKE_INSERT_FORBIDDEN');
    }
}

final class P7ReadOnlyFakeCatalog implements OdbcExplorerReadOnlyCatalogInterface
{
    public function dataSourceId(): string
    {
        return 'demo_odbc';
    }

    public function listTables(): array
    {
        return [
            new OdbcExplorerTableReference('users', 'TABLE', 'demo_catalog', 'public', 'Demo users'),
            new OdbcExplorerTableReference('invoices', 'TABLE', 'demo_catalog', 'public', 'Demo invoices'),
        ];
    }
}

function p7_assert_true(bool $condition, string $label): void
{
    if (!$condition) {
        fwrite(STDERR, $label . '=FAIL' . PHP_EOL);
        exit(1);
    }
    echo $label . '=OK' . PHP_EOL;
}

function p7_file_contains_forbidden_database_surface(string $directory): bool
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $content = (string) file_get_contents($file->getPathname());
        if (preg_match('/\b(PDO|mysqli_|mysql_|SQLite3|sqlite_|pgsql_)\b/i', $content)) {
            return true;
        }
    }

    return false;
}

echo 'P7_ODBC_EXPLORER_READONLY_CORE_SMOKE' . PHP_EOL;

$connection = new P7ReadOnlyFakeConnection();
$explorer = new OdbcExplorerService($connection);
$service = new OdbcExplorerReadOnlyService($explorer, new P7ReadOnlyFakeCatalog());

$overview = $service->dataSourceOverview();
p7_assert_true($overview['datasource'] === 'demo_odbc', 'CHECK_ODBC_READONLY_DATASOURCE');
p7_assert_true($overview['mode'] === 'readonly', 'CHECK_ODBC_READONLY_MODE');
p7_assert_true($overview['table_count'] === 2, 'CHECK_ODBC_READONLY_TABLE_COUNT');

$tables = $service->listTables();
p7_assert_true($tables[0]->qualifiedName() === 'public.users', 'CHECK_ODBC_READONLY_TABLE_QUALIFIED_NAME');
p7_assert_true($tables[0]->type() === 'TABLE', 'CHECK_ODBC_READONLY_TABLE_TYPE');

$inspection = $service->inspectTable('model.users', 'public.users');
p7_assert_true(($inspection['table']['qualified_name'] ?? null) === 'public.users', 'CHECK_ODBC_READONLY_INSPECT_TABLE');
p7_assert_true(count($inspection['columns']) === 3, 'CHECK_ODBC_READONLY_INSPECT_COLUMNS');
p7_assert_true(($inspection['model']['table'] ?? null) === 'public.users', 'CHECK_ODBC_READONLY_MODEL_TABLE');

$preview = $service->previewTable('model.users', 'users', 2);
p7_assert_true($preview['limit'] === 2, 'CHECK_ODBC_READONLY_PREVIEW_LIMIT');
p7_assert_true(count($preview['rows']) === 2, 'CHECK_ODBC_READONLY_PREVIEW_ROWS');
p7_assert_true(($preview['rows'][0]['email'] ?? null) === 'alpha@example.test', 'CHECK_ODBC_READONLY_PREVIEW_CONTENT');

$draft = $service->prepareLstsarDraftForTable('model.users', 'users');
p7_assert_true(($draft['contract'] ?? null) === 'OPUS_LSTSAR_ODBC_MODEL_DRAFT_V1', 'CHECK_ODBC_READONLY_LSTSAR_DRAFT');
p7_assert_true(($draft['guards']['odbc_only_database_access'] ?? false) === true, 'CHECK_ODBC_READONLY_LSTSAR_ODBC_ONLY');

try {
    $service->previewTable('model.missing', 'missing', 2);
    p7_assert_true(false, 'CHECK_ODBC_READONLY_UNKNOWN_TABLE_REJECTED');
} catch (RuntimeException $exception) {
    p7_assert_true(str_starts_with($exception->getMessage(), 'OPUS_ODBC_EXPLORER_UNKNOWN_TABLE:'), 'CHECK_ODBC_READONLY_UNKNOWN_TABLE_REJECTED');
}

p7_assert_true(!p7_file_contains_forbidden_database_surface(__DIR__ . '/../../Opus/OdbcExplorer/ReadOnly'), 'CHECK_ODBC_READONLY_ODBC_ONLY');

echo 'P7_ODBC_EXPLORER_READONLY_CORE_SMOKE_OK' . PHP_EOL;
