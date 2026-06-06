<?php

declare(strict_types=1);

/**
 * P112Q2B1 — Safe directory case normalization.
 *
 * This migration performs only the directories classified as safe by P112Q2A2:
 *
 * - ROUTING -> Routing
 * - SITE    -> Site
 * - URL     -> Url
 * - RENDER  -> Render
 *
 * It intentionally does not touch risky namespace/domain directories.
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

$renames = [
    'ROUTING' => 'Routing',
    'SITE' => 'Site',
    'URL' => 'Url',
    'RENDER' => 'Render',
];

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

function normalizeDirectoryCase(string $parent, string $from, string $to): void
{
    if ($from === $to) {
        throw new RuntimeException('INVALID_CASE_RENAME_IDENTITY: ' . $from);
    }

    if (!hasExactDirectorySegment($parent, $from)) {
        throw new RuntimeException('SOURCE_DIRECTORY_SEGMENT_NOT_FOUND_EXACT: ' . $from);
    }

    if (hasExactDirectorySegment($parent, $to)) {
        throw new RuntimeException('TARGET_DIRECTORY_SEGMENT_ALREADY_EXISTS_EXACT: ' . $to);
    }

    $tmp = '__P112Q2B1_CASE_TMP_' . $from . '_' . bin2hex(random_bytes(4));

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

    $files = new FilesystemIterator($targetPath, FilesystemIterator::SKIP_DOTS);

    if (!$files->valid()) {
        file_put_contents($targetPath . DIRECTORY_SEPARATOR . '.gitkeep', "P112Q2B1 keeps this normalized empty directory tracked.\n");
    }

    echo 'RENAMED ' . $from . ' -> ' . $to . PHP_EOL;
}

function shouldSkipPath(string $path): bool
{
    $normalized = str_replace('\\', '/', $path);

    return str_contains($normalized, '/.git/')
        || str_contains($normalized, '/vendor/')
        || str_contains($normalized, '/var/cache/')
        || str_contains($normalized, '/var/reports/')
        || str_contains($normalized, '/node_modules/');
}

function replaceInTextFiles(array $roots, array $replacements): int
{
    $allowedExtensions = [
        'php' => true,
        'md' => true,
        'json' => true,
        'xml' => true,
        'yml' => true,
        'yaml' => true,
        'txt' => true,
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

foreach ($renames as $from => $to) {
    normalizeDirectoryCase($frameworkRoot, $from, $to);
}

$replacements = [];

foreach ($renames as $from => $to) {
    $replacements['framework/Asap/' . $from] = 'framework/Asap/' . $to;
    $replacements['framework\\ASAP\\' . $from] = 'framework\\ASAP\\' . $to;
    $replacements['framework\\\\ASAP\\\\' . $from] = 'framework\\\\ASAP\\\\' . $to;

    $replacements['ASAP/' . $from] = 'ASAP/' . $to;
    $replacements['ASAP\\' . $from] = 'ASAP\\' . $to;
    $replacements['ASAP\\\\' . $from] = 'ASAP\\\\' . $to;

    $replacements['/' . $from . '/'] = '/' . $to . '/';
    $replacements['\\' . $from . '\\'] = '\\' . $to . '\\';
    $replacements['\\\\' . $from . '\\\\'] = '\\\\' . $to . '\\\\';
}

$changedFiles = replaceInTextFiles([$asapRoot, $refBookRoot], $replacements);

echo 'TEXT_FILES_UPDATED=' . $changedFiles . PHP_EOL;
echo 'P112Q2B1_SAFE_DIRECTORY_CASE_NORMALIZATION_OK' . PHP_EOL;

exit(0);
