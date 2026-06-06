<?php

declare(strict_types=1);

/**
 * P112Q2E — BDD to Database English domain rename.
 *
 * This migration handles the final P112Q2A2 finding:
 *
 * - BDD -> Database
 *
 * It performs:
 * - exact directory segment rename through a temporary path;
 * - namespace token normalization in runtime text files;
 * - path token normalization in runtime text files;
 * - no fallback aliases;
 * - no runtime autoload magic.
 */

$asapRoot = 'H:\\ASAP';
$refBookRoot = 'H:\\ASAP_REF_BOOK';
$frameworkRoot = $asapRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Asap';

if (!is_dir($asapRoot)) {
    fwrite(STDERR, "ASAP_ROOT_MISSING\n");
    exit(1);
}

if (!is_dir($refBookRoot)) {
    fwrite(STDERR, "ASAP_REF_BOOK_ROOT_MISSING\n");
    exit(1);
}

if (!is_dir($frameworkRoot)) {
    fwrite(STDERR, "ASAP_FRAMEWORK_ROOT_MISSING\n");
    exit(1);
}

$from = 'BDD';
$to = 'Database';

function exactDirectorySegments(string $parent): array
{
    $entries = scandir($parent);

    if ($entries === false) {
        throw new RuntimeException('SCANDIR_FAILED: ' . $parent);
    }

    return array_values(array_filter($entries, static function (string $entry) use ($parent): bool {
        return $entry !== '.' && $entry !== '..' && is_dir($parent . DIRECTORY_SEPARATOR . $entry);
    }));
}

function hasExactDirectorySegment(string $parent, string $segment): bool
{
    return in_array($segment, exactDirectorySegments($parent), true);
}

function normalizeDirectorySegment(string $parent, string $from, string $to): void
{
    if ($from === $to) {
        throw new RuntimeException('INVALID_DIRECTORY_RENAME_IDENTITY: ' . $from);
    }

    if (!hasExactDirectorySegment($parent, $from)) {
        throw new RuntimeException('SOURCE_DIRECTORY_SEGMENT_NOT_FOUND_EXACT: ' . $from);
    }

    if (hasExactDirectorySegment($parent, $to)) {
        throw new RuntimeException('TARGET_DIRECTORY_SEGMENT_ALREADY_EXISTS_EXACT: ' . $to);
    }

    $tmp = '__P112Q2E_RENAME_TMP_' . $from . '_' . bin2hex(random_bytes(4));

    if (hasExactDirectorySegment($parent, $tmp)) {
        throw new RuntimeException('TEMP_DIRECTORY_SEGMENT_ALREADY_EXISTS_EXACT: ' . $tmp);
    }

    $sourcePath = $parent . DIRECTORY_SEPARATOR . $from;
    $tmpPath = $parent . DIRECTORY_SEPARATOR . $tmp;
    $targetPath = $parent . DIRECTORY_SEPARATOR . $to;

    if (!rename($sourcePath, $tmpPath)) {
        throw new RuntimeException('DIRECTORY_RENAME_TO_TEMP_FAILED: ' . $from . ' -> ' . $tmp);
    }

    if (!rename($tmpPath, $targetPath)) {
        throw new RuntimeException('DIRECTORY_RENAME_TO_TARGET_FAILED: ' . $tmp . ' -> ' . $to);
    }

    if (!hasExactDirectorySegment($parent, $to)) {
        throw new RuntimeException('TARGET_DIRECTORY_SEGMENT_NOT_FOUND_AFTER_RENAME: ' . $to);
    }

    if (hasExactDirectorySegment($parent, $from)) {
        throw new RuntimeException('SOURCE_DIRECTORY_SEGMENT_STILL_PRESENT_AFTER_RENAME: ' . $from);
    }

    echo 'RENAMED ' . $from . ' -> ' . $to . PHP_EOL;
}

function normalizedPath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function shouldSkipPath(string $path): bool
{
    $normalized = normalizedPath($path);

    if (str_contains($normalized, '/.git/')) {
        return true;
    }

    if (str_contains($normalized, '/vendor/')) {
        return true;
    }

    if (str_contains($normalized, '/var/cache/')) {
        return true;
    }

    if (str_contains($normalized, '/var/reports/')) {
        return true;
    }

    if (str_contains($normalized, '/node_modules/')) {
        return true;
    }

    if (str_contains(strtolower($normalized), '/tools/migration/p112q2e_')) {
        return true;
    }

    if (str_contains($normalized, '/DOC/P112Q2E_')) {
        return true;
    }

    if (str_contains($normalized, '/content/markdown/bdd-to-database-english-domain-rename.md')) {
        return true;
    }

    return false;
}

function replaceInTextFiles(array $roots, array $replacements): int
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

    $changed = 0;

    foreach ($roots as $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $path = $item->getPathname();

            if (shouldSkipPath($path)) {
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

            $updated = str_replace(array_keys($replacements), array_values($replacements), $content);

            if ($updated !== $content) {
                if (@file_put_contents($path, $updated) === false) {
                    throw new RuntimeException('TEXT_FILE_WRITE_FAILED: ' . $path);
                }

                $changed++;
            }
        }
    }

    return $changed;
}

normalizeDirectorySegment($frameworkRoot, $from, $to);

$replacements = [
    'namespace ASAP\\BDD' => 'namespace ASAP\\Database',
    'use ASAP\\BDD' => 'use ASAP\\Database',
    'ASAP\\BDD' => 'ASAP\\Database',
    'ASAP\\\\BDD' => 'ASAP\\\\Database',
    'framework/Asap/BDD' => 'framework/Asap/Database',
    'framework\\ASAP\\BDD' => 'framework\\ASAP\\Database',
    'framework\\\\ASAP\\\\BDD' => 'framework\\\\ASAP\\\\Database',
    'ASAP/BDD' => 'ASAP/Database',
    '/BDD/' => '/Database/',
    '\\BDD\\' => '\\Database\\',
    '\\\\BDD\\\\' => '\\\\Database\\\\',
];

$changedFiles = replaceInTextFiles([$asapRoot, $refBookRoot], $replacements);

echo 'TEXT_FILES_UPDATED=' . $changedFiles . PHP_EOL;
echo 'P112Q2E_BDD_TO_DATABASE_ENGLISH_DOMAIN_RENAME_OK' . PHP_EOL;

exit(0);
