<?php
declare(strict_types=1);

namespace Opus\Scaffold;

use Opus\Console\OpusConsoleException;

/**
 * Writes OPUS scaffold plans.
 *
 * Contract:
 * - dry-run is safe and non-mutating;
 * - write never overwrites an existing file;
 * - target root must not already exist unless caller plan is designed for an existing site.
 */
final class ScaffoldWriter
 implements ScaffoldWriterInterface {
    public function __construct(private readonly string $opusRoot)
    {
    }

    public function assertPathDoesNotExist(string $relativePath): void
    {
        $absolute = $this->absolutePath($relativePath);

        if (file_exists($absolute)) {
            throw new OpusConsoleException("OPUS_SCAFFOLD_TARGET_ALREADY_EXISTS: {$relativePath}");
        }
    }

    public function assertDirectoryExists(string $relativePath): void
    {
        $absolute = $this->absolutePath($relativePath);

        if (!is_dir($absolute)) {
            throw new OpusConsoleException("OPUS_SCAFFOLD_REQUIRED_DIRECTORY_MISSING: {$relativePath}");
        }
    }

    public function renderPlan(ScaffoldPlanInterface $plan): void
    {
        foreach ($plan->entries() as $entry) {
            echo '[' . strtoupper($entry->type) . '] ' . $entry->relativePath . "\n";
        }
    }

    public function writePlan(ScaffoldPlanInterface $plan): void
    {
        foreach ($plan->entries() as $entry) {
            $absolute = $this->absolutePath($entry->relativePath);

            if ($entry->type === ScaffoldEntry::TYPE_DIRECTORY) {
                if (!is_dir($absolute) && !mkdir($absolute, 0775, true)) {
                    throw new OpusConsoleException("OPUS_SCAFFOLD_DIRECTORY_CREATE_FAILED: {$entry->relativePath}");
                }

                continue;
            }

            if ($entry->type !== ScaffoldEntry::TYPE_FILE) {
                throw new OpusConsoleException("OPUS_SCAFFOLD_ENTRY_TYPE_INVALID: {$entry->type}");
            }

            if (file_exists($absolute)) {
                throw new OpusConsoleException("OPUS_SCAFFOLD_FILE_ALREADY_EXISTS: {$entry->relativePath}");
            }

            $parent = dirname($absolute);
            if (!is_dir($parent) && !mkdir($parent, 0775, true)) {
                throw new OpusConsoleException("OPUS_SCAFFOLD_DIRECTORY_CREATE_FAILED: " . dirname($entry->relativePath));
            }

            if (file_put_contents($absolute, $entry->content) === false) {
                throw new OpusConsoleException("OPUS_SCAFFOLD_FILE_WRITE_FAILED: {$entry->relativePath}");
            }
        }
    }

    private function absolutePath(string $relativePath): string
    {
        return rtrim($this->opusRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }
}
