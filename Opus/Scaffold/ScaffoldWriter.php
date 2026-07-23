<?php
declare(strict_types=1);

namespace Opus\Scaffold;

use Opus\Console\OpusConsoleException;
use Opus\File\File;
use Opus\File\FileInterface;

/**
 * Writes canonical OPUS scaffold plans without overwriting existing content.
 *
 * File content crosses the OPUS File boundary and is written atomically. CLI
 * previews use the explicit standard-output stream and never produce web UI.
 */
final class ScaffoldWriter implements ScaffoldWriterInterface
{
    private readonly FileInterface $file;

    public function __construct(
        private readonly string $opusRoot,
        ?FileInterface $file = null
    ) {
        $this->file = $file ?? File::instance();
    }

    public function assertPathDoesNotExist(string $relativePath): void
    {
        $absolute = $this->absolutePath($relativePath);
        if (file_exists($absolute)) {
            throw new OpusConsoleException(
                'OPUS_SCAFFOLD_TARGET_ALREADY_EXISTS:' . $relativePath
            );
        }
    }

    public function assertDirectoryExists(string $relativePath): void
    {
        if (!is_dir($this->absolutePath($relativePath))) {
            throw new OpusConsoleException(
                'OPUS_SCAFFOLD_REQUIRED_DIRECTORY_MISSING:' . $relativePath
            );
        }
    }

    public function renderPlan(ScaffoldPlanInterface $plan): void
    {
        foreach ($plan->entries() as $entry) {
            $line = '[' . strtoupper($entry->type) . '] '
                . $entry->relativePath . PHP_EOL;
            if (fwrite(STDOUT, $line) === false) {
                throw new OpusConsoleException(
                    'OPUS_SCAFFOLD_PREVIEW_WRITE_FAILED'
                );
            }
        }
    }

    public function writePlan(ScaffoldPlanInterface $plan): void
    {
        foreach ($plan->entries() as $entry) {
            $absolute = $this->absolutePath($entry->relativePath);

            if ($entry->type === ScaffoldEntry::TYPE_DIRECTORY) {
                if (!is_dir($absolute)
                    && !mkdir($absolute, 0775, true)
                    && !is_dir($absolute)) {
                    throw new OpusConsoleException(
                        'OPUS_SCAFFOLD_DIRECTORY_CREATE_FAILED:'
                        . $entry->relativePath
                    );
                }
                continue;
            }

            if ($entry->type !== ScaffoldEntry::TYPE_FILE) {
                throw new OpusConsoleException(
                    'OPUS_SCAFFOLD_ENTRY_TYPE_INVALID:' . $entry->type
                );
            }
            if (file_exists($absolute)) {
                throw new OpusConsoleException(
                    'OPUS_SCAFFOLD_FILE_ALREADY_EXISTS:'
                    . $entry->relativePath
                );
            }

            $this->file->writeAtomic($absolute, $entry->content);
        }
    }

    private function absolutePath(string $relativePath): string
    {
        $normalized = trim(str_replace('\\', '/', $relativePath), '/');
        if ($normalized === ''
            || str_contains($normalized, '..')
            || str_contains($normalized, "\0")
            || preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            throw new OpusConsoleException(
                'OPUS_SCAFFOLD_RELATIVE_PATH_INVALID:' . $relativePath
            );
        }
        return rtrim($this->opusRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    }
}
