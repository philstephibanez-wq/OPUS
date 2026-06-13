<?php

declare(strict_types=1);

/**
 * P112Q2E recipe.
 *
 * Important:
 * The legacy forbidden tokens are built dynamically so a previous partial
 * migration cannot rewrite this recipe into checking valid Database tokens.
 */

$asapRoot = 'H:\\ASAP';
$refBookRoot = 'H:\\OPUS_REF_BOOK';
$frameworkRoot = $asapRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus';

if (!is_dir($frameworkRoot)) {
    throw new RuntimeException('OPUS_FRAMEWORK_ROOT_MISSING');
}

function exactDirectorySegmentsRecipe(string $parent): array
{
    $entries = scandir($parent);

    if ($entries === false) {
        throw new RuntimeException('SCANDIR_FAILED: ' . $parent);
    }

    return array_values(array_filter($entries, static function (string $entry) use ($parent): bool {
        return $entry !== '.' && $entry !== '..' && is_dir($parent . DIRECTORY_SEPARATOR . $entry);
    }));
}

function assertExactDirectoryState(string $parent, string $old, string $new): void
{
    $segments = exactDirectorySegmentsRecipe($parent);

    if (in_array($old, $segments, true)) {
        throw new RuntimeException('OLD_DIRECTORY_SEGMENT_STILL_PRESENT: ' . $old);
    }

    if (!in_array($new, $segments, true)) {
        throw new RuntimeException('NEW_DIRECTORY_SEGMENT_MISSING: ' . $new);
    }

    echo 'PASS DIRECTORY ' . $old . ' -> ' . $new . PHP_EOL;
}

function normalizedPathRecipe(string $path): string
{
    return str_replace('\\', '/', $path);
}

function shouldSkipScanPathRecipe(string $path): bool
{
    $normalized = normalizedPathRecipe($path);

    return str_contains($normalized, '/.git/')
        || str_contains($normalized, '/vendor/')
        || str_contains($normalized, '/var/cache/')
        || str_contains($normalized, '/var/reports/')
        || str_contains($normalized, '/node_modules/')
        || str_contains(strtolower($normalized), '/tools/migration/p112q2e_')
        || str_contains($normalized, '/DOC/P112Q2E_')
        || str_contains($normalized, '/DOC/P112Q2E_FIX_')
        || str_contains($normalized, '/content/markdown/bdd-to-database-english-domain-rename.md');
}

function assertNoRuntimeToken(array $roots, array $forbiddenTokens): void
{
    $allowedExtensions = [
        'php' => true,
        'json' => true,
        'xml' => true,
        'yml' => true,
        'yaml' => true,
        'cmd' => true,
        'ps1' => true,
        'html' => true,
        'twig' => true,
        'ini' => true,
    ];

    foreach ($roots as $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $path = $item->getPathname();

            if (shouldSkipScanPathRecipe($path)) {
                continue;
            }

            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (!isset($allowedExtensions[$extension])) {
                continue;
            }

            $content = @file_get_contents($path);

            if ($content === false) {
                throw new RuntimeException('TEXT_FILE_READ_FAILED: ' . $path);
            }

            foreach ($forbiddenTokens as $token) {
                if (str_contains($content, $token)) {
                    throw new RuntimeException('FORBIDDEN_RUNTIME_TOKEN_FOUND: ' . $token . ' in ' . $path);
                }
            }
        }
    }
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

function assertPhpFilesLintInRoot(string $root): void
{
    $php = 'H:\\UwAmp\\bin\\php\\php-8.5.6\\php.exe';

    if (!is_file($php)) {
        throw new RuntimeException('UWAMP_PHP_MISSING');
    }

    if (!is_dir($root)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if (!$item->isFile() || strtolower($item->getExtension()) !== 'php') {
            continue;
        }

        $path = $item->getPathname();

        if (shouldSkipScanPathRecipe($path)) {
            continue;
        }

        lintPhpFile($php, $path);
    }
}

$legacy = 'BD' . 'D';

assertExactDirectoryState($frameworkRoot, $legacy, 'Database');

$forbidden = [
    'ASAP' . '\\' . $legacy,
    'ASAP' . '\\\\' . $legacy,
    'framework/Opus/' . $legacy,
    'framework' . '\\' . 'ASAP' . '\\' . $legacy,
    'framework' . '\\\\' . 'ASAP' . '\\\\' . $legacy,
    '/' . $legacy . '/',
    '\\' . $legacy . '\\',
];

assertNoRuntimeToken([$asapRoot, $refBookRoot], $forbidden);

echo 'PASS NO_OLD_DATABASE_NAMESPACE_OR_PATH_TOKENS' . PHP_EOL;

require_once $frameworkRoot . '/Database/Database.php';
require_once $frameworkRoot . '/Database/Mysql.php';

if (!class_exists(\ASAP\Database\Database::class)) {
    throw new RuntimeException('OPUS_DATABASE_DATABASE_CLASS_NOT_LOADABLE');
}

if (!class_exists(\ASAP\Database\Mysql::class)) {
    throw new RuntimeException('OPUS_DATABASE_MYSQL_CLASS_NOT_LOADABLE');
}

echo 'PASS DATABASE_CLASSES_LOADABLE' . PHP_EOL;

assertPhpFilesLintInRoot($frameworkRoot);
assertPhpFilesLintInRoot($asapRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'recipe');
assertPhpFilesLintInRoot($asapRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures');

echo 'PASS PHP_LINT_FRAMEWORK_RECIPE_FIXTURES' . PHP_EOL;
echo 'P112Q2E_BDD_TO_DATABASE_ENGLISH_DOMAIN_RENAME_RECIPE_OK' . PHP_EOL;
