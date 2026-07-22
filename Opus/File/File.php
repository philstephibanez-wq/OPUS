<?php
declare(strict_types=1);

namespace Opus\File;

/**
 * Canonical local-file boundary for OPUS.
 *
 * Configuration parsers receive strings from this component and never read files
 * directly. Reads are bounded and atomic writes stay in the target directory.
 */
final class File implements FileInterface
{
    public const CONTRACT = 'OPUS_FILE_V1';
    private const DEFAULT_MAX_BYTES = 16777216;

    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function exists(string $path): bool
    {
        $path = $this->path($path);
        return is_file($path) && is_readable($path);
    }

    public function read(string $path, ?int $maxBytes = null): string
    {
        $path = $this->path($path);
        $limit = $maxBytes ?? self::DEFAULT_MAX_BYTES;
        if ($limit < 1) {
            throw new \InvalidArgumentException('OPUS_FILE_MAX_BYTES_INVALID');
        }
        if (!$this->exists($path)) {
            throw new \RuntimeException('OPUS_FILE_NOT_READABLE:' . $path);
        }
        $size = filesize($path);
        if ($size === false || $size > $limit) {
            throw new \RuntimeException('OPUS_FILE_SIZE_INVALID:' . $path);
        }
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('OPUS_FILE_OPEN_FAILED:' . $path);
        }
        try {
            $contents = '';
            while (!feof($stream)) {
                $chunk = fread($stream, min(8192, $limit + 1 - strlen($contents)));
                if ($chunk === false) {
                    throw new \RuntimeException('OPUS_FILE_READ_FAILED:' . $path);
                }
                $contents .= $chunk;
                if (strlen($contents) > $limit) {
                    throw new \RuntimeException('OPUS_FILE_SIZE_INVALID:' . $path);
                }
            }
            return $contents;
        } finally {
            fclose($stream);
        }
    }

    public function writeAtomic(string $path, string $contents): void
    {
        $path = $this->path($path);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('OPUS_FILE_DIRECTORY_CREATE_FAILED:' . $directory);
        }
        $temporary = $directory . DIRECTORY_SEPARATOR . '.' . basename($path)
            . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $stream = fopen($temporary, 'xb');
        if ($stream === false) {
            throw new \RuntimeException('OPUS_FILE_TEMP_OPEN_FAILED:' . $temporary);
        }
        try {
            if (!flock($stream, LOCK_EX)) {
                throw new \RuntimeException('OPUS_FILE_LOCK_FAILED:' . $temporary);
            }
            $offset = 0;
            $length = strlen($contents);
            while ($offset < $length) {
                $written = fwrite($stream, substr($contents, $offset));
                if ($written === false || $written < 1) {
                    throw new \RuntimeException('OPUS_FILE_WRITE_FAILED:' . $temporary);
                }
                $offset += $written;
            }
            fflush($stream);
            flock($stream, LOCK_UN);
        } finally {
            fclose($stream);
        }

        $backup = null;
        try {
            if (is_file($path)) {
                $backup = $path . '.' . bin2hex(random_bytes(8)) . '.bak';
                if (!rename($path, $backup)) {
                    throw new \RuntimeException('OPUS_FILE_BACKUP_FAILED:' . $path);
                }
            }
            if (!rename($temporary, $path)) {
                throw new \RuntimeException('OPUS_FILE_REPLACE_FAILED:' . $path);
            }
            if ($backup !== null && is_file($backup) && !unlink($backup)) {
                throw new \RuntimeException('OPUS_FILE_BACKUP_DELETE_FAILED:' . $backup);
            }
        } catch (\Throwable $error) {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
            if ($backup !== null && is_file($backup) && !is_file($path)) {
                @rename($backup, $path);
            }
            throw $error;
        }
    }

    public function delete(string $path): void
    {
        $path = $this->path($path);
        if (!is_file($path)) {
            return;
        }
        if (!unlink($path)) {
            throw new \RuntimeException('OPUS_FILE_DELETE_FAILED:' . $path);
        }
    }

    public function matching(string $pattern): array
    {
        $pattern = $this->path($pattern);
        $matches = glob($pattern, GLOB_NOSORT);
        if ($matches === false) {
            throw new \RuntimeException('OPUS_FILE_GLOB_FAILED:' . $pattern);
        }
        $files = array_values(array_filter($matches, 'is_file'));
        sort($files, SORT_STRING);
        return $files;
    }

    public function extension(string $path): string
    {
        return strtolower(pathinfo($this->path($path), PATHINFO_EXTENSION));
    }

    private function path(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('OPUS_FILE_PATH_INVALID');
        }
        return $path;
    }
}
