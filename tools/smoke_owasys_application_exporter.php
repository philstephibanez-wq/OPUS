<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Opus\Owasys\ApplicationExporter;

$root = dirname(__DIR__);
$siteId = 'demo-app';
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . $siteId;
$outputDir = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'owasys-export-smoke';
$outputZip = $outputDir . DIRECTORY_SEPARATOR . $siteId . '.zip';

if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "OWASYS_EXPORT_ZIP_EXTENSION_MISSING\n");
    exit(1);
}

if (!is_dir($siteRoot)) {
    fwrite(STDERR, "OWASYS_EXPORT_SMOKE_SITE_MISSING: {$siteId}\n");
    exit(1);
}

function owasys_export_smoke_remove_tree(string $path): void
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

function owasys_export_smoke_write_file(string $path, string $content): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('OWASYS_EXPORT_SMOKE_DIR_CREATE_FAILED: ' . $dir);
    }
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException('OWASYS_EXPORT_SMOKE_FILE_WRITE_FAILED: ' . $path);
    }
}

owasys_export_smoke_remove_tree($outputDir);
@unlink($siteRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'owasys-export-smoke.tmp');
@unlink($siteRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'owasys-export-smoke.log');
@unlink($siteRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'registry' . DIRECTORY_SEPARATOR . 'owasys-export-smoke.sqlite');

try {
    owasys_export_smoke_write_file($siteRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'owasys-export-smoke.tmp', 'cache residue');
    owasys_export_smoke_write_file($siteRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'owasys-export-smoke.log', 'log residue');
    owasys_export_smoke_write_file($siteRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'registry' . DIRECTORY_SEPARATOR . 'owasys-export-smoke.sqlite', 'sqlite residue');

    $summary = (new ApplicationExporter($root))->export($siteId, $outputZip, false);
    if (($summary['site_id'] ?? null) !== $siteId) {
        throw new RuntimeException('OWASYS_EXPORT_SMOKE_SITE_ID_INVALID');
    }
    if (!is_file($outputZip)) {
        throw new RuntimeException('OWASYS_EXPORT_SMOKE_ZIP_MISSING');
    }

    $zip = new ZipArchive();
    if ($zip->open($outputZip) !== true) {
        throw new RuntimeException('OWASYS_EXPORT_SMOKE_ZIP_OPEN_FAILED');
    }

    foreach (['MANIFEST.json', 'config/site.json', 'config/routes.json', 'application/home/views/index.php', 'www/index.php'] as $required) {
        if ($zip->locateName($required) === false) {
            $zip->close();
            throw new RuntimeException('OWASYS_EXPORT_SMOKE_REQUIRED_ENTRY_MISSING: ' . $required);
        }
    }

    foreach (['var/cache/owasys-export-smoke.tmp', 'var/log/owasys-export-smoke.log', 'var/registry/owasys-export-smoke.sqlite'] as $forbidden) {
        if ($zip->locateName($forbidden) !== false) {
            $zip->close();
            throw new RuntimeException('OWASYS_EXPORT_SMOKE_FORBIDDEN_ENTRY_PRESENT: ' . $forbidden);
        }
    }

    $manifestJson = $zip->getFromName('MANIFEST.json');
    $zip->close();
    $manifest = is_string($manifestJson) ? json_decode($manifestJson, true) : null;
    if (!is_array($manifest) || ($manifest['contract'] ?? null) !== 'OWASYS_APPLICATION_EXPORT_MANIFEST_V1') {
        throw new RuntimeException('OWASYS_EXPORT_SMOKE_MANIFEST_INVALID');
    }
} finally {
    owasys_export_smoke_remove_tree($outputDir);
    @unlink($siteRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'owasys-export-smoke.tmp');
    @unlink($siteRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'owasys-export-smoke.log');
    @unlink($siteRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'registry' . DIRECTORY_SEPARATOR . 'owasys-export-smoke.sqlite');
    @rmdir($siteRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache');
    @rmdir($siteRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'log');
    @rmdir($siteRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'registry');
    @rmdir($siteRoot . DIRECTORY_SEPARATOR . 'var');
}

if (is_file($outputZip)) {
    fwrite(STDERR, "OWASYS_EXPORT_SMOKE_ZIP_CLEANUP_FAILED\n");
    exit(1);
}

echo "OWASYS_APPLICATION_EXPORTER_SMOKE_OK\n";
