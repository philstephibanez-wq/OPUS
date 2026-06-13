<?php

declare(strict_types=1);

/**
 * P112Q2D â€” Namespace and directory case normalization.
 *
 * This migration handles the remaining P112Q2A2 directories classified as:
 *
 * RISKY_NAMESPACE_AND_DIRECTORY_RENAME
 *
 * It excludes BDD -> Database, which is an English domain rename and remains a
 * separate semantic step.
 *
 * The migration performs:
 * - exact directory segment normalization through a temporary path;
 * - namespace token normalization in runtime text files;
 * - path token normalization in runtime text files;
 * - no fallback aliases;
 * - no runtime autoload magic.
 */

$asapRoot = 'H:\\ASAP';
$refBookRoot = 'H:\\OPUS_REF_BOOK';
$frameworkRoot = $asapRoot . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus';

if (!is_dir($asapRoot)) {
    fwrite(STDERR, "OPUS_ROOT_MISSING\n");
    exit(1);
}

if (!is_dir($refBookRoot)) {
    fwrite(STDERR, "OPUS_REF_BOOK_ROOT_MISSING\n");
    exit(1);
}

if (!is_dir($frameworkRoot)) {
    fwrite(STDERR, "OPUS_FRAMEWORK_ROOT_MISSING\n");
    exit(1);
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

    $tmp = '__P112Q2D_CASE_TMP_' . $from . '_' . bin2hex(random_bytes(4));

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

    if (str_contains(strtolower($normalized), '/tools/migration/p112q2d_')) {
        return true;
    }

    if (str_contains($normalized, '/DOC/P112Q2D_')) {
        return true;
    }

    if (str_contains($normalized, '/content/markdown/namespace-directory-case-normalization.md')) {
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

foreach ($renames as $from => $to) {
    normalizeDirectoryCase($frameworkRoot, $from, $to);
}

$replacements = [];

foreach ($renames as $from => $to) {
    $replacements['namespace Opus\\' . $from] = 'namespace Opus\\' . $to;
    $replacements['use Opus\\' . $from] = 'use Opus\\' . $to;
    $replacements['Opus\\' . $from] = 'Opus\\' . $to;
    $replacements['Opus\\\\' . $from] = 'Opus\\\\' . $to;

    $replacements['framework/Opus/' . $from] = 'framework/Opus/' . $to;
    $replacements['framework\\Opus\\' . $from] = 'framework\\Opus\\' . $to;
    $replacements['framework\\\\Opus\\\\' . $from] = 'framework\\\\Opus\\\\' . $to;

    $replacements['ASAP/' . $from] = 'ASAP/' . $to;
    $replacements['/' . $from . '/'] = '/' . $to . '/';
    $replacements['\\' . $from . '\\'] = '\\' . $to . '\\';
    $replacements['\\\\' . $from . '\\\\'] = '\\\\' . $to . '\\\\';
}

$changedFiles = replaceInTextFiles([$asapRoot, $refBookRoot], $replacements);

echo 'TEXT_FILES_UPDATED=' . $changedFiles . PHP_EOL;
echo 'P112Q2D_NAMESPACE_DIRECTORY_CASE_NORMALIZATION_OK' . PHP_EOL;

exit(0);
