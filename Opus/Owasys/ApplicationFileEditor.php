<?php
declare(strict_types=1);

namespace Opus\Owasys;

use RuntimeException;

/**
 * Safe editor for files owned by one OPUS application.
 *
 * The editor never accepts absolute paths, never follows paths outside the
 * application root and only permits explicit application directories/types.
 */
final class ApplicationFileEditor
{
    public const CONTRACT = 'OWASYS_APPLICATION_FILE_EDITOR_V1';

    /** @var list<string> */
    private const ALLOWED_PREFIXES = [
        'config/',
        'application/',
        'www/asset/',
    ];

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = [
        'php', 'score', 'css', 'js', 'json', 'md', 'txt', 'yml', 'yaml',
    ];

    /** @var list<string> */
    private const FORBIDDEN_BASENAMES = [
        '.env', '.htpasswd', 'id_rsa', 'id_ed25519', 'local-users.json',
    ];

    public function __construct(private readonly string $opusRoot)
    {
    }

    /** @return list<array{path:string,bytes:int,extension:string}> */
    public function listFiles(string $applicationRoot): array
    {
        $root = $this->resolveApplicationRoot($applicationRoot);
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $entry) {
            if (!$entry instanceof \SplFileInfo || !$entry->isFile() || $entry->isLink()) {
                continue;
            }
            $relative = $this->relativePath($root, $entry->getPathname());
            if (!$this->isEditableRelativePath($relative)) {
                continue;
            }
            $files[] = [
                'path' => $relative,
                'bytes' => (int) $entry->getSize(),
                'extension' => strtolower((string) pathinfo($relative, PATHINFO_EXTENSION)),
            ];
        }

        usort($files, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));
        return $files;
    }

    /** @return array<string,mixed> */
    public function read(string $applicationRoot, string $relativePath): array
    {
        $root = $this->resolveApplicationRoot($applicationRoot);
        $file = $this->resolveEditableFile($root, $relativePath, true);
        $content = file_get_contents($file);
        if (!is_string($content)) {
            throw new RuntimeException('OWASYS_EDITOR_READ_FAILED');
        }

        return [
            'contract' => self::CONTRACT,
            'application_root' => $this->relativeToOpus($root),
            'path' => $this->relativePath($root, $file),
            'content' => $content,
            'bytes' => strlen($content),
            'sha256' => hash('sha256', $content),
            'writable' => is_writable($file),
        ];
    }

    /** @return array<string,mixed> */
    public function preview(string $applicationRoot, string $relativePath, string $newContent): array
    {
        $current = $this->read($applicationRoot, $relativePath);
        $this->validateContent((string) $current['path'], $newContent);

        return [
            'contract' => self::CONTRACT,
            'mode' => 'preview',
            'application_root' => $current['application_root'],
            'path' => $current['path'],
            'changed' => !hash_equals((string) $current['sha256'], hash('sha256', $newContent)),
            'before_sha256' => $current['sha256'],
            'after_sha256' => hash('sha256', $newContent),
            'before_bytes' => $current['bytes'],
            'after_bytes' => strlen($newContent),
            'diff' => $this->simpleDiff((string) $current['content'], $newContent),
            'validation' => ['status' => 'ok'],
            'disk_mutation' => false,
        ];
    }

    /** @return array<string,mixed> */
    public function write(string $applicationRoot, string $relativePath, string $newContent, string $expectedSha256): array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $expectedSha256) !== 1) {
            throw new RuntimeException('OWASYS_EDITOR_EXPECTED_SHA256_INVALID');
        }

        $root = $this->resolveApplicationRoot($applicationRoot);
        $file = $this->resolveEditableFile($root, $relativePath, true);
        $current = file_get_contents($file);
        if (!is_string($current)) {
            throw new RuntimeException('OWASYS_EDITOR_READ_FAILED');
        }
        if (!hash_equals($expectedSha256, hash('sha256', $current))) {
            throw new RuntimeException('OWASYS_EDITOR_CONCURRENT_MODIFICATION');
        }

        $this->validateContent($this->relativePath($root, $file), $newContent);
        $temporary = $file . '.owasys-' . bin2hex(random_bytes(8)) . '.tmp';
        if (file_put_contents($temporary, $newContent, LOCK_EX) === false) {
            throw new RuntimeException('OWASYS_EDITOR_TEMP_WRITE_FAILED');
        }

        try {
            $this->validateFile($this->relativePath($root, $file), $temporary);
            if (!rename($temporary, $file)) {
                throw new RuntimeException('OWASYS_EDITOR_ATOMIC_REPLACE_FAILED');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }

        return [
            'contract' => self::CONTRACT,
            'mode' => 'write',
            'application_root' => $this->relativeToOpus($root),
            'path' => $this->relativePath($root, $file),
            'sha256' => hash('sha256', $newContent),
            'bytes' => strlen($newContent),
            'validation' => ['status' => 'ok'],
            'disk_mutation' => true,
            'atomic' => true,
        ];
    }

    private function validateContent(string $relativePath, string $content): void
    {
        if (str_contains($content, "\0")) {
            throw new RuntimeException('OWASYS_EDITOR_BINARY_CONTENT_FORBIDDEN');
        }
        if (strlen($content) > 2_000_000) {
            throw new RuntimeException('OWASYS_EDITOR_CONTENT_TOO_LARGE');
        }

        $extension = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));
        if ($extension === 'json') {
            json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('OWASYS_EDITOR_JSON_INVALID');
            }
        }
    }

    private function validateFile(string $relativePath, string $temporaryFile): void
    {
        $extension = strtolower((string) pathinfo($relativePath, PATHINFO_EXTENSION));
        if ($extension !== 'php') {
            return;
        }

        $output = [];
        $code = 0;
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($temporaryFile) . ' 2>&1', $output, $code);
        if ($code !== 0) {
            throw new RuntimeException('OWASYS_EDITOR_PHP_SYNTAX_INVALID');
        }
    }

    private function resolveApplicationRoot(string $applicationRoot): string
    {
        $relative = trim(str_replace('\\', '/', $applicationRoot), '/');
        if ($relative === '' || str_contains($relative, '..') || preg_match('/^[A-Za-z]:/', $relative) === 1) {
            throw new RuntimeException('OWASYS_EDITOR_APPLICATION_ROOT_INVALID');
        }
        $path = realpath($this->resolvedOpusRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if (!is_string($path) || !is_dir($path) || !$this->isInside($path, $this->resolvedOpusRoot())) {
            throw new RuntimeException('OWASYS_EDITOR_APPLICATION_ROOT_MISSING');
        }
        return $path;
    }

    private function resolveEditableFile(string $applicationRoot, string $relativePath, bool $mustExist): string
    {
        $relative = trim(str_replace('\\', '/', $relativePath), '/');
        if (!$this->isEditableRelativePath($relative)) {
            throw new RuntimeException('OWASYS_EDITOR_PATH_FORBIDDEN');
        }

        $candidate = $applicationRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if ($mustExist) {
            $resolved = realpath($candidate);
            if (!is_string($resolved) || !is_file($resolved) || is_link($candidate) || !$this->isInside($resolved, $applicationRoot)) {
                throw new RuntimeException('OWASYS_EDITOR_FILE_MISSING');
            }
            return $resolved;
        }
        return $candidate;
    }

    private function isEditableRelativePath(string $relative): bool
    {
        if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/') || str_contains($relative, '\\')) {
            return false;
        }
        $lower = strtolower($relative);
        if (str_starts_with($lower, '.git/') || str_contains($lower, '/.git/')) {
            return false;
        }
        if (in_array(strtolower(basename($relative)), self::FORBIDDEN_BASENAMES, true)) {
            return false;
        }
        $allowedPrefix = false;
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                $allowedPrefix = true;
                break;
            }
        }
        if (!$allowedPrefix) {
            return false;
        }
        $extension = strtolower((string) pathinfo($relative, PATHINFO_EXTENSION));
        return in_array($extension, self::ALLOWED_EXTENSIONS, true);
    }

    private function simpleDiff(string $before, string $after): string
    {
        if ($before === $after) {
            return '';
        }
        $beforeLines = preg_split('/\R/', $before) ?: [];
        $afterLines = preg_split('/\R/', $after) ?: [];
        $lines = ['--- current', '+++ proposed'];
        $max = max(count($beforeLines), count($afterLines));
        for ($index = 0; $index < $max; $index++) {
            $old = $beforeLines[$index] ?? null;
            $new = $afterLines[$index] ?? null;
            if ($old === $new) {
                continue;
            }
            if ($old !== null) {
                $lines[] = '-' . $old;
            }
            if ($new !== null) {
                $lines[] = '+' . $new;
            }
        }
        return implode("\n", $lines) . "\n";
    }

    private function resolvedOpusRoot(): string
    {
        $root = realpath($this->opusRoot);
        if (!is_string($root) || !is_dir($root)) {
            throw new RuntimeException('OWASYS_OPUS_ROOT_INVALID');
        }
        return $root;
    }

    private function relativeToOpus(string $path): string
    {
        return $this->relativePath($this->resolvedOpusRoot(), $path);
    }

    private function relativePath(string $root, string $path): string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $path = str_replace('\\', '/', $path);
        return ltrim(substr($path, strlen($root)), '/');
    }

    private function isInside(string $path, string $root): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $root = rtrim(str_replace('\\', '/', $root), '/');
        return $path === $root || str_starts_with($path . '/', $root . '/');
    }
}
