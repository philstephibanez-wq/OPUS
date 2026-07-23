<?php
declare(strict_types=1);

namespace Opus\Console\Service;

use Opus\Console\OpusConsoleException;

/** Exports one canonical OPUS site as a ZIP archive. */
final class SiteArchiveExporter implements SiteArchiveExporterInterface
{
    private readonly string $opusRoot;

    public function __construct(string $opusRoot)
    {
        $root = rtrim(str_replace('\\', '/', $opusRoot), '/');
        if ($root === '' || !is_dir($root)) {
            throw new OpusConsoleException('OPUS_EXPORT_ROOT_INVALID');
        }
        $this->opusRoot = $root;
    }

    public function export(string $siteId, string $outputRelative, bool $overwrite): array
    {
        $siteId = trim(strtolower($siteId));
        if (preg_match('/^[a-z][a-z0-9-]*$/', $siteId) !== 1) {
            throw new OpusConsoleException('OPUS_EXPORT_SITE_ID_INVALID');
        }
        if (!class_exists(\ZipArchive::class)) {
            throw new OpusConsoleException('OPUS_EXPORT_ZIP_EXTENSION_MISSING');
        }

        $siteRoot = $this->opusRoot . '/sites/' . $siteId;
        if (!is_dir($siteRoot)) {
            throw new OpusConsoleException('OPUS_SITE_NOT_FOUND:' . $siteId);
        }

        $outputRelative = trim(str_replace('\\', '/', $outputRelative), '/');
        if ($outputRelative === '') {
            $outputRelative = 'runtime/exports/' . $siteId . '.zip';
        }
        if (
            str_contains($outputRelative, '..')
            || preg_match('/^[A-Za-z]:\//', $outputRelative) === 1
            || !str_ends_with(strtolower($outputRelative), '.zip')
        ) {
            throw new OpusConsoleException('OPUS_EXPORT_OUTPUT_PATH_INVALID');
        }

        $output = $this->opusRoot . '/' . $outputRelative;
        if (is_file($output) && !$overwrite) {
            throw new OpusConsoleException('OPUS_EXPORT_OUTPUT_EXISTS:' . $outputRelative);
        }
        $directory = dirname($output);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new OpusConsoleException('OPUS_EXPORT_DIRECTORY_CREATE_FAILED');
        }

        $zip = new \ZipArchive();
        $flags = \ZipArchive::CREATE | \ZipArchive::OVERWRITE;
        if ($zip->open($output, $flags) !== true) {
            throw new OpusConsoleException('OPUS_EXPORT_ZIP_OPEN_FAILED');
        }

        $files = 0;
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $siteRoot,
                    \FilesystemIterator::SKIP_DOTS
                ),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                    continue;
                }
                $absolute = str_replace('\\', '/', $file->getPathname());
                $relative = 'sites/' . $siteId . '/'
                    . ltrim(substr($absolute, strlen(str_replace('\\', '/', $siteRoot))), '/');
                if (!$zip->addFile($absolute, $relative)) {
                    throw new OpusConsoleException('OPUS_EXPORT_ADD_FILE_FAILED:' . $relative);
                }
                ++$files;
            }
        } finally {
            $zip->close();
        }

        return [
            'contract' => 'OPUS_CONSOLE_SITE_EXPORT_RESULT_V1',
            'site_id' => $siteId,
            'output_zip' => $outputRelative,
            'files' => $files,
            'bytes' => is_file($output) ? (int) filesize($output) : 0,
        ];
    }
}
