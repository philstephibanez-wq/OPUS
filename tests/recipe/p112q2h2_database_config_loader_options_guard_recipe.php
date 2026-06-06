<?php

declare(strict_types=1);

$root = 'H:\\ASAP';
$framework = $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap';

if (!is_dir($framework)) {
    throw new RuntimeException('ASAP_FRAMEWORK_ROOT_MISSING');
}

require_once $framework . '/Database/DatabaseException.php';
require_once $framework . '/Database/DatabaseProvider.php';
require_once $framework . '/Database/DatabaseConnectionConfig.php';
require_once $framework . '/Database/DatabaseDsnFactory.php';
require_once $framework . '/Database/DatabaseConfigLoader.php';

$loader = new \ASAP\Database\DatabaseConfigLoader();

$withoutOptions = simplexml_load_string(
    '<database provider="sqlite"><path>H:/ASAP_REF_BOOK/var/data/asap.sqlite</path></database>'
);

if (!$withoutOptions instanceof SimpleXMLElement) {
    throw new RuntimeException('XML_WITHOUT_OPTIONS_INVALID');
}

$configWithoutOptions = $loader->fromXml($withoutOptions, 'without-options');

if ($configWithoutOptions->pdoOptions !== []) {
    throw new RuntimeException('MISSING_OPTIONS_SHOULD_PRODUCE_EMPTY_ARRAY');
}

echo 'PASS MISSING_OPTIONS_EMPTY_ARRAY' . PHP_EOL;

$withOptions = simplexml_load_string(
    '<database provider="mysql"><host>127.0.0.1</host><database>maestro</database><options><option name="ATTR_TIMEOUT" value="5"/></options></database>'
);

if (!$withOptions instanceof SimpleXMLElement) {
    throw new RuntimeException('XML_WITH_OPTIONS_INVALID');
}

$configWithOptions = $loader->fromXml($withOptions, 'with-options');

if (($configWithOptions->pdoOptions['ATTR_TIMEOUT'] ?? null) !== '5') {
    throw new RuntimeException('DATABASE_OPTIONS_NOT_LOADED');
}

echo 'PASS OPTIONS_LOADED' . PHP_EOL;

$badOption = simplexml_load_string(
    '<database provider="mysql"><host>127.0.0.1</host><database>maestro</database><options><option value="5"/></options></database>'
);

if (!$badOption instanceof SimpleXMLElement) {
    throw new RuntimeException('XML_BAD_OPTION_INVALID');
}

try {
    $loader->fromXml($badOption, 'bad-option');
    throw new RuntimeException('EMPTY_OPTION_NAME_DID_NOT_FAIL');
} catch (\ASAP\Database\DatabaseException) {
    echo 'PASS EMPTY_OPTION_NAME_FAILS' . PHP_EOL;
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
echo 'P112Q2H2_DATABASE_CONFIG_LOADER_OPTIONS_GUARD_RECIPE_OK' . PHP_EOL;
