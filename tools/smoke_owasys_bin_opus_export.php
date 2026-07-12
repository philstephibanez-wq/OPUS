<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$siteId = 'demo-app';
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . $siteId;
$outputDir = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'owasys-bin-export-smoke';
$outputRelative = 'var/owasys-bin-export-smoke/demo-app.zip';
$outputZip = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $outputRelative);

/**
 * Removes a directory tree created by this smoke test.
 */
function owasys_bin_export_remove_tree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $itemPath = $item->getPathname();
        if ($item->isDir()) {
            @rmdir($itemPath);
        } else {
            @unlink($itemPath);
        }
    }

    @rmdir($path);
}

/**
 * Executes a shell command and captures output.
 *
 * @return array{code:int,output:string}
 */
function owasys_bin_export_exec(string $command): array
{
    $lines = [];
    $code = 0;
    exec($command . ' 2>&1', $lines, $code);
    return ['code' => (int) $code, 'output' => implode("\n", $lines)];
}

if (!is_dir($siteRoot)) {
    fwrite(STDERR, "OWASYS_BIN_OPUS_EXPORT_SITE_MISSING: {$siteId}\n");
    exit(1);
}

if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "OWASYS_BIN_OPUS_EXPORT_ZIP_EXTENSION_MISSING\n");
    exit(1);
}

owasys_bin_export_remove_tree($outputDir);

try {
    $command = PHP_BINARY . ' ' . escapeshellarg($root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'opus')
        . ' owasys:export ' . escapeshellarg($siteId)
        . ' ' . escapeshellarg($outputRelative)
        . ' --overwrite';

    $export = owasys_bin_export_exec($command);
    if ($export['code'] !== 0 || !str_contains($export['output'], 'OWASYS_APPLICATION_EXPORT_OK: ' . $outputRelative)) {
        fwrite(STDERR, $export['output'] . "\n");
        throw new RuntimeException('OWASYS_BIN_OPUS_EXPORT_FAILED');
    }

    if (!is_file($outputZip)) {
        throw new RuntimeException('OWASYS_BIN_OPUS_EXPORT_ZIP_MISSING');
    }

    $zip = new ZipArchive();
    if ($zip->open($outputZip) !== true) {
        throw new RuntimeException('OWASYS_BIN_OPUS_EXPORT_ZIP_OPEN_FAILED');
    }

    foreach (['MANIFEST.json', 'config/site.json', 'config/routes.json', 'application/home/views/index.php', 'www/index.php'] as $required) {
        if ($zip->locateName($required) === false) {
            $zip->close();
            throw new RuntimeException('OWASYS_BIN_OPUS_EXPORT_REQUIRED_ENTRY_MISSING: ' . $required);
        }
    }

    $manifestJson = $zip->getFromName('MANIFEST.json');
    $zip->close();

    if (!is_string($manifestJson)) {
        throw new RuntimeException('OWASYS_BIN_OPUS_EXPORT_MANIFEST_MISSING');
    }

    $manifest = json_decode($manifestJson, true);
    if (!is_array($manifest) || ($manifest['contract'] ?? null) !== 'OWASYS_APPLICATION_EXPORT_MANIFEST_V1') {
        throw new RuntimeException('OWASYS_BIN_OPUS_EXPORT_MANIFEST_INVALID');
    }

    if (str_contains(str_replace('\\', '/', $manifestJson), str_replace('\\', '/', $root))) {
        throw new RuntimeException('OWASYS_BIN_OPUS_EXPORT_MANIFEST_ABSOLUTE_PATH_LEAK');
    }
} finally {
    owasys_bin_export_remove_tree($outputDir);
}

if (is_file($outputZip)) {
    fwrite(STDERR, "OWASYS_BIN_OPUS_EXPORT_SMOKE_CLEANUP_FAILED\n");
    exit(1);
}

echo "OWASYS_BIN_OPUS_OWASYS_EXPORT_SMOKE_OK\n";
