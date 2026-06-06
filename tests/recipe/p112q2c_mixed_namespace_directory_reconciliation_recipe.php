<?php

declare(strict_types=1);

/**
 * P112Q2C recipe.
 *
 * Verifies that mixed namespace/directory segments have been normalized.
 *
 * Important:
 * The forbidden-token list is intentionally built by concatenation so the
 * P112Q2C migration text replacement cannot rewrite the test expectations
 * themselves when the migration is rerun after a partial failure.
 */

$asapRoot = 'H:\\ASAP';
$refBookRoot = 'H:\\ASAP_REF_BOOK';
$frameworkRoot = $asapRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap';

if (!is_dir($frameworkRoot)) {
    throw new RuntimeException('ASAP_FRAMEWORK_ROOT_MISSING');
}

$renames = [
    'HELPER' => 'Helper',
    'MENU' => 'Menu',
    'TEMPLATE' => 'Template',
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
        || str_contains(strtolower($normalized), '/tools/migration/p112q2c_')
        || str_contains($normalized, '/DOC/P112Q2C_')
        || str_contains($normalized, '/DOC/P112Q2C_FIX_')
        || str_contains($normalized, '/content/markdown/mixed-namespace-directory-reconciliation.md');
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

foreach ($renames as $old => $new) {
    assertExactDirectoryState($frameworkRoot, $old, $new);
}

/*
 * Build these strings dynamically to avoid the migration replacing the
 * assertions while it normalizes runtime files.
 */
$upperHelper = 'HELP' . 'ER';
$upperMenu = 'ME' . 'NU';
$upperTemplate = 'TEMP' . 'LATE';

$forbidden = [
    'ASAP\\' . $upperHelper,
    'ASAP\\' . $upperMenu,
    'ASAP\\' . $upperTemplate,
    'ASAP\\\\' . $upperHelper,
    'ASAP\\\\' . $upperMenu,
    'ASAP\\\\' . $upperTemplate,
    'framework/Asap/' . $upperHelper,
    'framework/Asap/' . $upperMenu,
    'framework/Asap/' . $upperTemplate,
    'framework\\ASAP\\' . $upperHelper,
    'framework\\ASAP\\' . $upperMenu,
    'framework\\ASAP\\' . $upperTemplate,
];

assertNoRuntimeToken([$asapRoot, $refBookRoot], $forbidden);

echo 'PASS NO_OLD_RUNTIME_NAMESPACE_OR_PATH_TOKENS' . PHP_EOL;
echo 'P112Q2C_MIXED_NAMESPACE_DIRECTORY_RECONCILIATION_RECIPE_OK' . PHP_EOL;
