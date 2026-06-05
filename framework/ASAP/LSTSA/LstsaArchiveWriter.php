<?php

declare(strict_types=1);

namespace ASAP\LSTSA;

/**
 * PUBLIC LSTSA ARCHIVE WRITER
 *
 * Role:
 *   Write append-only JSON and Markdown reports for one run.
 */
final class LstsaArchiveWriter
{
    /**
     * @return array{json:string,markdown:string}
     */
    public function writeReport(LstsaReport $report, string $directory): array
    {
        if (trim($directory) === '') {
            throw LstsaException::because('ASAP_LSTSA_ARCHIVE_DIRECTORY_EMPTY');
        }

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw LstsaException::because('ASAP_LSTSA_ARCHIVE_DIRECTORY_CREATE_FAILED', $directory);
        }

        $base = $this->safeName($report->runId());
        $jsonPath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $base . '.json';
        $mdPath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $base . '.md';

        foreach ([$jsonPath, $mdPath] as $path) {
            if (file_exists($path)) {
                throw LstsaException::because('ASAP_LSTSA_ARCHIVE_APPEND_ONLY_VIOLATION', $path);
            }
        }

        $this->writeFile($jsonPath, $report->toJson());
        $this->writeFile($mdPath, $report->toMarkdown());

        return ['json' => $jsonPath, 'markdown' => $mdPath];
    }

    private function writeFile(string $path, string $content): void
    {
        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw LstsaException::because('ASAP_LSTSA_ARCHIVE_WRITE_FAILED', $path);
        }
    }

    private function safeName(string $name): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $name) ?? '';
        $safe = trim($safe, '._-');

        if ($safe === '') {
            throw LstsaException::because('ASAP_LSTSA_ARCHIVE_RUN_ID_INVALID', $name);
        }

        return $safe;
    }
}
