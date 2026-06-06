<?php

declare(strict_types=1);

/**
 * P112Q2D recipe.
 *
 * Verifies the broad namespace/directory case normalization.
 *
 * The lint scope is intentionally limited to the framework runtime plus current
 * recipe/fixture files. Legacy smoke tests are not part of the Q2D migration
 * contract and may contain pre-existing syntax-policy issues.
 */

$asapRoot = 'H:\\ASAP';
$refBookRoot = 'H:\\ASAP_REF_BOOK';
$frameworkRoot = $asapRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap';

if (!is_dir($frameworkRoot)) {
    throw new RuntimeException('ASAP_FRAMEWORK_ROOT_MISSING');
}

$renames = [
    'ACL' => 'Acl',
    'ACTION' => 'Action',
    'CACHE' => 'Cache',
    'CONTROLLER' => 'Controller',
    'COOKIE' => 'Cookie',
    'CSS' => 'Css',
    'DATE' => 'Date',
    'DIRECTORY' => 'Directory',
    'EVENT' => 'Event',
    'FILE' => 'File',
    'FSM' => 'Fsm',
    'FTP' => 'Ftp',
    'I18N' => 'I18n',
    'JS' => 'Javascript',
    'JSON' => 'Json',
    'LANGUAGE' => 'Language',
    'LINK' => 'Link',
    'LOG' => 'Log',
    'MAIL' => 'Mail',
    'MODEL' => 'Model',
    'REQUEST' => 'Request',
    'RESPONSE' => 'Response',
    'REST' => 'Rest',
    'ROUTER' => 'Router',
    'SESSION' => 'Session',
    'SMTP' => 'Smtp',
    'VIEW' => 'View',
    'XML' => 'Xml',
];

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
        || str_contains(strtolower($normalized), '/tools/migration/p112q2d_')
        || str_contains($normalized, '/DOC/P112Q2D_')
        || str_contains($normalized, '/DOC/P112Q2D_FIX_')
        || str_contains($normalized, '/content/markdown/namespace-directory-case-normalization.md');
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

foreach ($renames as $old => $new) {
    assertExactDirectoryState($frameworkRoot, $old, $new);
}

$forbidden = [];

foreach (array_keys($renames) as $old) {
    $forbidden[] = 'ASAP' . '\\' . $old;
    $forbidden[] = 'ASAP' . '\\\\' . $old;
    $forbidden[] = 'framework/Asap/' . $old;
    $forbidden[] = 'framework' . '\\' . 'ASAP' . '\\' . $old;
    $forbidden[] = 'framework' . '\\\\' . 'ASAP' . '\\\\' . $old;
}

assertNoRuntimeToken([$asapRoot, $refBookRoot], $forbidden);

echo 'PASS NO_OLD_RUNTIME_NAMESPACE_OR_PATH_TOKENS' . PHP_EOL;

assertPhpFilesLintInRoot($frameworkRoot);
assertPhpFilesLintInRoot($asapRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'recipe');
assertPhpFilesLintInRoot($asapRoot . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures');

echo 'PASS PHP_LINT_FRAMEWORK_RECIPE_FIXTURES' . PHP_EOL;
echo 'P112Q2D_NAMESPACE_DIRECTORY_CASE_NORMALIZATION_RECIPE_OK' . PHP_EOL;
