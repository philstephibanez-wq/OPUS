<?php

declare(strict_types=1);

$root = 'H:\\ASAP';
$framework = $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap';

if (!is_dir($framework)) {
    throw new RuntimeException('ASAP_FRAMEWORK_ROOT_MISSING');
}

require_once $framework . '/Contract/ContractException.php';
require_once $framework . '/Http/Request.php';
require_once $framework . '/Site/SiteDefinition.php';
require_once $framework . '/Site/SiteResolver.php';
require_once $framework . '/Database/DatabaseException.php';
require_once $framework . '/Database/DatabaseProvider.php';
require_once $framework . '/Database/DatabaseConnectionConfig.php';
require_once $framework . '/Database/DatabaseDsnFactory.php';
require_once $framework . '/Database/DatabaseConfigLoader.php';

$requiredClasses = [
    \ASAP\Database\DatabaseException::class,
    \ASAP\Database\DatabaseProvider::class,
    \ASAP\Database\DatabaseConnectionConfig::class,
    \ASAP\Database\DatabaseDsnFactory::class,
    \ASAP\Database\DatabaseConfigLoader::class,
    \ASAP\Site\SiteDefinition::class,
    \ASAP\Site\SiteResolver::class,
];

foreach ($requiredClasses as $class) {
    if (!class_exists($class)) {
        throw new RuntimeException('CLASS_NOT_LOADABLE: ' . $class);
    }
}

echo 'PASS DATABASE_CLASSES_LOADABLE' . PHP_EOL;

$factory = new \ASAP\Database\DatabaseDsnFactory();

$mysql = new \ASAP\Database\DatabaseConnectionConfig('mysql', null, 'user', 'secret', [
    'host' => '127.0.0.1',
    'database' => 'maestro',
    'port' => '3306',
]);

if ($factory->build($mysql) !== 'mysql:host=127.0.0.1;port=3306;dbname=maestro;charset=utf8mb4') {
    throw new RuntimeException('MYSQL_DSN_FAILED');
}

$pgsql = new \ASAP\Database\DatabaseConnectionConfig('postgresql', null, 'user', 'secret', [
    'host' => '127.0.0.1',
    'database' => 'maestro',
    'port' => '5432',
]);

if ($factory->build($pgsql) !== 'pgsql:host=127.0.0.1;port=5432;dbname=maestro') {
    throw new RuntimeException('POSTGRESQL_DSN_FAILED');
}

$sqlite = new \ASAP\Database\DatabaseConnectionConfig('sqlite3', null, null, null, [
    'path' => 'H:/ASAP_REF_BOOK/var/data/asap.sqlite',
]);

if ($factory->build($sqlite) !== 'sqlite:H:/ASAP_REF_BOOK/var/data/asap.sqlite') {
    throw new RuntimeException('SQLITE_DSN_FAILED');
}

$oracle = new \ASAP\Database\DatabaseConnectionConfig('oracle', null, 'user', 'secret', [
    'host' => '127.0.0.1',
    'service' => 'XE',
]);

if ($factory->build($oracle) !== 'oci:dbname=//127.0.0.1:1521/XE;charset=AL32UTF8') {
    throw new RuntimeException('ORACLE_DSN_FAILED');
}

$odbc = new \ASAP\Database\DatabaseConnectionConfig('odbc', null, null, null, [
    'name' => 'ASAP_DSN',
]);

if ($factory->build($odbc) !== 'odbc:ASAP_DSN') {
    throw new RuntimeException('ODBC_DSN_FAILED');
}

$sqlserver = new \ASAP\Database\DatabaseConnectionConfig('sqlsrv', null, 'user', 'secret', [
    'host' => '127.0.0.1',
    'database' => 'maestro',
    'port' => '1433',
]);

if ($factory->build($sqlserver) !== 'sqlsrv:Server=127.0.0.1,1433;Database=maestro') {
    throw new RuntimeException('SQLSERVER_DSN_FAILED');
}

echo 'PASS DATABASE_DSN_FACTORY' . PHP_EOL;

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'asap_p112q2h_site_' . bin2hex(random_bytes(4));
$site = $tmp . DIRECTORY_SEPARATOR . 'demo-site';

if (!mkdir($site, 0777, true) && !is_dir($site)) {
    throw new RuntimeException('TMP_SITE_CREATE_FAILED');
}

file_put_contents($site . DIRECTORY_SEPARATOR . 'routes.xml', '<routes></routes>');
file_put_contents($site . DIRECTORY_SEPARATOR . 'security.xml', '<security></security>');
file_put_contents(
    $site . DIRECTORY_SEPARATOR . 'database.xml',
    '<database provider="sqlite"><path>H:/ASAP_REF_BOOK/var/data/asap.sqlite</path></database>'
);
file_put_contents(
    $site . DIRECTORY_SEPARATOR . 'site.xml',
    '<site id="demo"><basePath>/demo</basePath><routes file="routes.xml"/><security file="security.xml"/><database file="database.xml"/></site>'
);

$resolver = new \ASAP\Site\SiteResolver($tmp);
$definition = $resolver->resolve(new \ASAP\Http\Request('/demo/page', 'GET'));

if (!$definition->hasDatabase()) {
    throw new RuntimeException('SITE_DATABASE_NOT_DECLARED');
}

if (basename($definition->requireDatabaseFile()) !== 'database.xml') {
    throw new RuntimeException('SITE_DATABASE_FILE_FAILED');
}

$config = (new \ASAP\Database\DatabaseConfigLoader())->loadXmlFile($definition->requireDatabaseFile());

if ($config->normalizedProvider() !== \ASAP\Database\DatabaseProvider::SQLITE) {
    throw new RuntimeException('SITE_DATABASE_PROVIDER_FAILED');
}

if ($factory->build($config) !== 'sqlite:H:/ASAP_REF_BOOK/var/data/asap.sqlite') {
    throw new RuntimeException('SITE_DATABASE_CONFIG_DSN_FAILED');
}

echo 'PASS SITE_DATABASE_CONFIG' . PHP_EOL;

try {
    new \ASAP\Database\DatabaseConnectionConfig('unknown-provider');
    throw new RuntimeException('UNSUPPORTED_PROVIDER_DID_NOT_FAIL');
} catch (\ASAP\Database\DatabaseException) {
    echo 'PASS UNSUPPORTED_PROVIDER_FAILS' . PHP_EOL;
}

function lintPhpFile(string $php, string $path): void
{
    $cmd = '"' . $php . '" -l ' . escapeshellarg($path) . ' 2>&1';
    $output = [];
    exec($cmd, $output, $code);

    if ($code !== 0) {
        throw new RuntimeException('PHP_LINT_FAILED: ' . $path . ' :: ' . implode(' ', $output));
    }
}

function lintRoot(string $root): void
{
    $php = 'H:\\UwAmp\\bin\\php\\php-8.5.6\\php.exe';

    if (!is_file($php)) {
        throw new RuntimeException('UWAMP_PHP_MISSING');
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if (!$item->isFile() || strtolower($item->getExtension()) !== 'php') {
            continue;
        }

        $normalized = str_replace('\\', '/', $item->getPathname());

        if (str_contains($normalized, '/vendor/') || str_contains($normalized, '/var/cache/') || str_contains($normalized, '/var/reports/')) {
            continue;
        }

        lintPhpFile($php, $item->getPathname());
    }
}

lintRoot($framework);
lintRoot($root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'recipe');
lintRoot($root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures');

echo 'PASS PHP_LINT_FRAMEWORK_RECIPE_FIXTURES' . PHP_EOL;
echo 'P112Q2H_DATABASE_PROVIDER_SITE_CONFIG_FOUNDATION_RECIPE_OK' . PHP_EOL;
