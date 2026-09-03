<?php
declare(strict_types=1);

namespace Opus\Application\Source;

use Opus\File\File;
use Opus\File\StructuredFileLoader;
use Opus\Log\LoggerInterface;
use Opus\Profiler\ProfilerInterface;
use Throwable;

/**
 * Generic OPUS source workspace with bounded reads, optimistic locking,
 * diff preview, atomic writes and bounded multi-file transactions. Source
 * contents never enter logs or traces.
 */
final class SiteSourceWorkspace implements SiteSourceWorkspaceInterface
{
    public const CONTRACT = 'OPUS_SITE_SOURCE_WORKSPACE_V1';
    private const DEFAULT_MAX_BYTES = 1048576;
    private const MAX_FILES = 5000;
    private const MAX_BATCH_FILES = 16;
    private const MAX_DIFF_BYTES = 262144;

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = [
        'php', 'json', 'js', 'mjs', 'cjs', 'css', 'html', 'htm', 'sql',
        'md', 'markdown', 'score', 'xml', 'yaml', 'yml', 'txt',
    ];

    /** @var list<string> */
    private const BLOCKED_NAMES = [
        '.env', '.env.local', '.env.production', '.env.development',
        'auth.json', 'composer-auth.json', 'composer.auth.json',
    ];

    /** @var list<string> */
    private const BLOCKED_SEGMENTS = [
        '.git', 'vendor', 'node_modules', 'var', 'cache', 'logs', 'tmp',
    ];

    private readonly string $sitesRoot;
    private readonly File $files;
    private readonly StructuredFileLoader $loader;

    public function __construct(
        string $opusRoot,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?ProfilerInterface $profiler = null,
        private readonly ?string $parentSpanId = null
    ) {
        $realRoot = realpath($opusRoot);
        if (!is_string($realRoot) || !is_dir($realRoot)) {
            throw new \RuntimeException('OPUS_SITE_SOURCE_OPUS_ROOT_INVALID');
        }
        $sitesRoot = realpath($realRoot . '/sites');
        if (!is_string($sitesRoot) || !is_dir($sitesRoot)) {
            throw new \RuntimeException('OPUS_SITE_SOURCE_SITES_ROOT_INVALID');
        }
        $this->sitesRoot = rtrim(str_replace('\\', '/', $sitesRoot), '/');
        $this->files = File::instance();
        $this->loader = StructuredFileLoader::instance();
    }

    public function list(
        string $siteId,
        int $maxFiles = self::MAX_FILES,
        int $maxBytes = self::DEFAULT_MAX_BYTES
    ): array {
        return $this->observed(
            'list',
            ['application_id' => $siteId],
            function () use ($siteId, $maxFiles, $maxBytes): array {
                $this->assertMaxFiles($maxFiles);
                $this->assertMaxBytes($maxBytes);
                $siteRoot = $this->siteRoot($siteId);
                $result = [];
                $truncated = false;
                $directory = new \RecursiveDirectoryIterator(
                    $siteRoot,
                    \FilesystemIterator::SKIP_DOTS
                );
                $filtered = new \RecursiveCallbackFilterIterator(
                    $directory,
                    function (\SplFileInfo $candidate): bool {
                        if (!$candidate->isDir()) {
                            return true;
                        }
                        if ($candidate->isLink()) {
                            return false;
                        }
                        $name = strtolower($candidate->getFilename());
                        return !in_array(
                            $name,
                            self::BLOCKED_SEGMENTS,
                            true
                        )
                            && !in_array(
                                $name,
                                self::BLOCKED_NAMES,
                                true
                            )
                            && !str_starts_with($name, '.env.');
                    }
                );
                $iterator = new \RecursiveIteratorIterator(
                    $filtered,
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($iterator as $candidate) {
                    if (!$candidate instanceof \SplFileInfo
                        || !$candidate->isFile()
                        || $candidate->isLink()) {
                        continue;
                    }
                    $pathname = str_replace(
                        '\\',
                        '/',
                        $candidate->getPathname()
                    );
                    if (!str_starts_with($pathname, $siteRoot . '/')) {
                        continue;
                    }
                    $relative = substr(
                        $pathname,
                        strlen($siteRoot) + 1
                    );
                    if (!$this->allowed($relative)) {
                        continue;
                    }
                    $size = $candidate->getSize();
                    if ($size < 0 || $size > $maxBytes) {
                        continue;
                    }
                    $result[] = [
                        'path' => $relative,
                        'extension' => strtolower((string) pathinfo(
                            $relative,
                            PATHINFO_EXTENSION
                        )),
                        'bytes' => $size,
                    ];
                    if (count($result) >= $maxFiles) {
                        $truncated = $iterator->valid();
                        break;
                    }
                }

                usort(
                    $result,
                    static fn (array $left, array $right): int => strcmp(
                        (string) $left['path'],
                        (string) $right['path']
                    )
                );

                return [
                    'contract' => 'OPUS_SITE_SOURCE_LIST_V2',
                    'application_id' => strtolower(trim($siteId)),
                    'writable' => true,
                    'truncated' => $truncated,
                    'max_bytes' => $maxBytes,
                    'files' => $result,
                ];
            }
        );
    }

    public function read(
        string $siteId,
        string $relativePath,
        int $maxBytes = self::DEFAULT_MAX_BYTES
    ): array {
        return $this->observed(
            'read',
            [
                'application_id' => $siteId,
                'path' => $relativePath,
            ],
            function () use ($siteId, $relativePath, $maxBytes): array {
                $this->assertMaxBytes($maxBytes);
                $siteRoot = $this->siteRoot($siteId);
                $relative = $this->relative($relativePath);
                $target = $this->existingTarget(
                    $siteRoot,
                    $relative
                );
                $content = $this->files->read($target, $maxBytes);
                $this->assertText($content);

                return $this->filePayload(
                    strtolower(trim($siteId)),
                    $relative,
                    $content,
                    $maxBytes
                );
            }
        );
    }

    public function preview(
        string $siteId,
        string $relativePath,
        string $expectedContentHash,
        string $newContent,
        int $maxBytes = self::DEFAULT_MAX_BYTES
    ): array {
        return $this->observed(
            'preview',
            [
                'application_id' => $siteId,
                'path' => $relativePath,
                'proposed_bytes' => strlen($newContent),
            ],
            function () use (
                $siteId,
                $relativePath,
                $expectedContentHash,
                $newContent,
                $maxBytes
            ): array {
                $prepared = $this->prepare(
                    $siteId,
                    $relativePath,
                    $expectedContentHash,
                    $newContent,
                    $maxBytes
                );

                return $this->previewPayload(
                    $prepared['application_id'],
                    $prepared['relative'],
                    $prepared['current'],
                    $newContent,
                    $maxBytes
                );
            }
        );
    }

    public function write(
        string $siteId,
        string $relativePath,
        string $expectedContentHash,
        string $newContent,
        int $maxBytes = self::DEFAULT_MAX_BYTES
    ): array {
        return $this->observed(
            'write',
            [
                'application_id' => $siteId,
                'path' => $relativePath,
                'proposed_bytes' => strlen($newContent),
            ],
            function () use (
                $siteId,
                $relativePath,
                $expectedContentHash,
                $newContent,
                $maxBytes
            ): array {
                $prepared = $this->prepare(
                    $siteId,
                    $relativePath,
                    $expectedContentHash,
                    $newContent,
                    $maxBytes
                );
                $preview = $this->previewPayload(
                    $prepared['application_id'],
                    $prepared['relative'],
                    $prepared['current'],
                    $newContent,
                    $maxBytes
                );

                if ($preview['changed'] !== true) {
                    return [
                        'contract' => 'OPUS_SITE_SOURCE_WRITE_V1',
                        'application_id' => $prepared['application_id'],
                        'path' => $prepared['relative'],
                        'changed' => false,
                        'previous_sha256' => $preview['current_sha256'],
                        'sha256' => $preview['proposed_sha256'],
                        'bytes' => strlen($newContent),
                        'diff' => $preview['diff'],
                        'diff_truncated' => $preview['diff_truncated'],
                    ];
                }

                $lock = $this->lock(
                    $prepared['site_root'],
                    $prepared['relative']
                );
                try {
                    $target = $this->existingTarget(
                        $prepared['site_root'],
                        $prepared['relative']
                    );
                    if ($target !== $prepared['target']) {
                        throw new \RuntimeException(
                            'OPUS_SITE_SOURCE_TARGET_CHANGED'
                        );
                    }
                    $current = $this->files->read($target, $maxBytes);
                    $this->assertText($current);
                    $currentHash = hash('sha256', $current);
                    if (!hash_equals(
                        $this->hash($expectedContentHash),
                        $currentHash
                    )) {
                        throw new \RuntimeException(
                            'OPUS_SITE_SOURCE_CONFLICT'
                        );
                    }

                    $this->files->writeAtomic($target, $newContent);
                    $writtenTarget = $this->existingTarget(
                        $prepared['site_root'],
                        $prepared['relative']
                    );
                    $written = $this->files->read(
                        $writtenTarget,
                        $maxBytes
                    );
                    $writtenHash = hash('sha256', $written);
                    $expectedWrittenHash = hash('sha256', $newContent);
                    if (!hash_equals($expectedWrittenHash, $writtenHash)) {
                        throw new \RuntimeException(
                            'OPUS_SITE_SOURCE_WRITE_VERIFY_FAILED'
                        );
                    }
                } finally {
                    $this->unlock($lock);
                }

                return [
                    'contract' => 'OPUS_SITE_SOURCE_WRITE_V1',
                    'application_id' => $prepared['application_id'],
                    'path' => $prepared['relative'],
                    'changed' => true,
                    'previous_sha256' => $preview['current_sha256'],
                    'sha256' => hash('sha256', $newContent),
                    'bytes' => strlen($newContent),
                    'diff' => $preview['diff'],
                    'diff_truncated' => $preview['diff_truncated'],
                ];
            }
        );
    }

    public function writeBatch(
        string $siteId,
        array $changes,
        int $maxBytes = self::DEFAULT_MAX_BYTES
    ): array {
        return $this->observed(
            'write_batch',
            [
                'application_id' => $siteId,
                'file_count' => count($changes),
            ],
            function () use ($siteId, $changes, $maxBytes): array {
                if (!array_is_list($changes)
                    || $changes === []
                    || count($changes) > self::MAX_BATCH_FILES) {
                    throw new \InvalidArgumentException(
                        'OPUS_SITE_SOURCE_BATCH_INVALID'
                    );
                }

                $prepared = [];
                foreach ($changes as $change) {
                    if (!is_array($change)
                        || array_is_list($change)
                        || !is_string($change['path'] ?? null)
                        || !is_string($change['expected_sha256'] ?? null)
                        || !is_string($change['content'] ?? null)) {
                        throw new \InvalidArgumentException(
                            'OPUS_SITE_SOURCE_BATCH_CHANGE_INVALID'
                        );
                    }
                    $item = $this->prepare(
                        $siteId,
                        $change['path'],
                        $change['expected_sha256'],
                        $change['content'],
                        $maxBytes
                    );
                    if (isset($prepared[$item['relative']])) {
                        throw new \InvalidArgumentException(
                            'OPUS_SITE_SOURCE_BATCH_PATH_DUPLICATE'
                        );
                    }
                    $item['proposed'] = $change['content'];
                    $prepared[$item['relative']] = $item;
                }
                ksort($prepared, SORT_STRING);

                $locks = [];
                $written = [];
                try {
                    foreach ($prepared as $relative => $item) {
                        $locks[$relative] = $this->lock(
                            $item['site_root'],
                            $relative
                        );
                    }

                    foreach ($prepared as $item) {
                        $target = $this->existingTarget(
                            $item['site_root'],
                            $item['relative']
                        );
                        if ($target !== $item['target']) {
                            throw new \RuntimeException(
                                'OPUS_SITE_SOURCE_TARGET_CHANGED'
                            );
                        }
                        $current = $this->files->read($target, $maxBytes);
                        $this->assertText($current);
                        if (!hash_equals(
                            hash('sha256', $item['current']),
                            hash('sha256', $current)
                        )) {
                            throw new \RuntimeException(
                                'OPUS_SITE_SOURCE_CONFLICT'
                            );
                        }
                    }

                    $files = [];
                    foreach ($prepared as $relative => $item) {
                        $proposed = $item['proposed'];
                        $changed = $item['current'] !== $proposed;
                        if ($changed) {
                            $this->files->writeAtomic(
                                $item['target'],
                                $proposed
                            );
                            $written[] = $relative;
                            $verified = $this->files->read(
                                $this->existingTarget(
                                    $item['site_root'],
                                    $relative
                                ),
                                $maxBytes
                            );
                            if (!hash_equals(
                                hash('sha256', $proposed),
                                hash('sha256', $verified)
                            )) {
                                throw new \RuntimeException(
                                    'OPUS_SITE_SOURCE_WRITE_VERIFY_FAILED'
                                );
                            }
                        }
                        $files[] = [
                            'path' => $relative,
                            'changed' => $changed,
                            'previous_sha256' => hash(
                                'sha256',
                                $item['current']
                            ),
                            'sha256' => hash('sha256', $proposed),
                            'bytes' => strlen($proposed),
                        ];
                    }

                    return [
                        'contract' => 'OPUS_SITE_SOURCE_BATCH_WRITE_V1',
                        'application_id' => strtolower(trim($siteId)),
                        'changed' => $written !== [],
                        'files' => $files,
                    ];
                } catch (Throwable $error) {
                    $rollbackFailed = false;
                    foreach (array_reverse($written) as $relative) {
                        $item = $prepared[$relative];
                        try {
                            $this->files->writeAtomic(
                                $item['target'],
                                $item['current']
                            );
                            $restored = $this->files->read(
                                $item['target'],
                                $maxBytes
                            );
                            if (!hash_equals(
                                hash('sha256', $item['current']),
                                hash('sha256', $restored)
                            )) {
                                $rollbackFailed = true;
                            }
                        } catch (Throwable) {
                            $rollbackFailed = true;
                        }
                    }
                    if ($rollbackFailed) {
                        throw new \RuntimeException(
                            'OPUS_SITE_SOURCE_BATCH_ROLLBACK_FAILED',
                            0,
                            $error
                        );
                    }
                    throw $error;
                } finally {
                    foreach (array_reverse($locks, true) as $lock) {
                        $this->unlock($lock);
                    }
                }
            }
        );
    }

    /**
     * @return array{
     *   application_id:string,
     *   site_root:string,
     *   relative:string,
     *   target:string,
     *   current:string
     * }
     */
    private function prepare(
        string $siteId,
        string $relativePath,
        string $expectedContentHash,
        string $newContent,
        int $maxBytes
    ): array {
        $this->assertMaxBytes($maxBytes);
        if (strlen($newContent) > $maxBytes) {
            throw new \RuntimeException(
                'OPUS_SITE_SOURCE_PROPOSED_SIZE_INVALID'
            );
        }
        $this->assertText($newContent);
        $expected = $this->hash($expectedContentHash);
        $siteRoot = $this->siteRoot($siteId);
        $relative = $this->relative($relativePath);
        $target = $this->existingTarget($siteRoot, $relative);
        $current = $this->files->read($target, $maxBytes);
        $this->assertText($current);
        if (!hash_equals($expected, hash('sha256', $current))) {
            throw new \RuntimeException('OPUS_SITE_SOURCE_CONFLICT');
        }

        return [
            'application_id' => strtolower(trim($siteId)),
            'site_root' => $siteRoot,
            'relative' => $relative,
            'target' => $target,
            'current' => $current,
        ];
    }

    /** @return array<string,mixed> */
    private function filePayload(
        string $siteId,
        string $relative,
        string $content,
        int $maxBytes
    ): array {
        return [
            'contract' => 'OPUS_SITE_SOURCE_FILE_V2',
            'application_id' => $siteId,
            'path' => $relative,
            'extension' => strtolower((string) pathinfo(
                $relative,
                PATHINFO_EXTENSION
            )),
            'bytes' => strlen($content),
            'lines' => $this->lineCount($content),
            'newline' => $this->newline($content),
            'sha256' => hash('sha256', $content),
            'max_bytes' => $maxBytes,
            'content' => $content,
            'writable' => true,
        ];
    }

    /** @return array<string,mixed> */
    private function previewPayload(
        string $siteId,
        string $relative,
        string $current,
        string $proposed,
        int $maxBytes
    ): array {
        $diff = $this->diff($relative, $current, $proposed);

        return [
            'contract' => 'OPUS_SITE_SOURCE_PREVIEW_V1',
            'application_id' => $siteId,
            'path' => $relative,
            'changed' => $current !== $proposed,
            'current_sha256' => hash('sha256', $current),
            'proposed_sha256' => hash('sha256', $proposed),
            'current_bytes' => strlen($current),
            'proposed_bytes' => strlen($proposed),
            'max_bytes' => $maxBytes,
            'diff' => $diff['content'],
            'diff_truncated' => $diff['truncated'],
        ];
    }

    /** @return array{content:string,truncated:bool} */
    private function diff(
        string $relative,
        string $current,
        string $proposed
    ): array {
        $header = '--- a/' . $relative . "\n"
            . '+++ b/' . $relative . "\n";
        if ($current === $proposed) {
            return ['content' => $header, 'truncated' => false];
        }

        $oldLines = $this->lines($current);
        $newLines = $this->lines($proposed);
        $oldCount = count($oldLines);
        $newCount = count($newLines);
        $prefix = 0;
        while ($prefix < $oldCount
            && $prefix < $newCount
            && $oldLines[$prefix] === $newLines[$prefix]) {
            $prefix++;
        }
        $suffix = 0;
        while ($suffix < ($oldCount - $prefix)
            && $suffix < ($newCount - $prefix)
            && $oldLines[$oldCount - 1 - $suffix]
                === $newLines[$newCount - 1 - $suffix]) {
            $suffix++;
        }

        $contextBeforeCount = min(3, $prefix);
        $contextAfterCount = min(3, $suffix);
        $contextStart = $prefix - $contextBeforeCount;
        $removed = array_slice(
            $oldLines,
            $prefix,
            $oldCount - $prefix - $suffix
        );
        $added = array_slice(
            $newLines,
            $prefix,
            $newCount - $prefix - $suffix
        );
        $before = array_slice(
            $oldLines,
            $contextStart,
            $contextBeforeCount
        );
        $after = array_slice(
            $oldLines,
            $oldCount - $suffix,
            $contextAfterCount
        );
        $oldRangeCount = count($before) + count($removed) + count($after);
        $newRangeCount = count($before) + count($added) + count($after);
        $oldStart = $oldRangeCount === 0 ? 0 : $contextStart + 1;
        $newStart = $newRangeCount === 0 ? 0 : $contextStart + 1;

        $body = '@@ -' . $oldStart . ',' . $oldRangeCount
            . ' +' . $newStart . ',' . $newRangeCount . " @@\n";
        foreach ($before as $line) {
            $body .= ' ' . $line . "\n";
        }
        foreach ($removed as $line) {
            $body .= '-' . $line . "\n";
        }
        foreach ($added as $line) {
            $body .= '+' . $line . "\n";
        }
        foreach ($after as $line) {
            $body .= ' ' . $line . "\n";
        }

        $result = $header . $body;
        if (strlen($result) <= self::MAX_DIFF_BYTES) {
            return ['content' => $result, 'truncated' => false];
        }

        return [
            'content' => substr($result, 0, self::MAX_DIFF_BYTES)
                . "\n... OPUS_DIFF_TRUNCATED ...\n",
            'truncated' => true,
        ];
    }

    /** @return list<string> */
    private function lines(string $content): array
    {
        if ($content === '') {
            return [];
        }
        $lines = preg_split('/\r\n|\n|\r/', $content);
        if (!is_array($lines)) {
            throw new \RuntimeException('OPUS_SITE_SOURCE_DIFF_FAILED');
        }
        return array_values(array_map('strval', $lines));
    }

    private function lineCount(string $content): int
    {
        return $content === ''
            ? 0
            : count($this->lines($content));
    }

    private function newline(string $content): string
    {
        if (str_contains($content, "\r\n")) {
            return 'crlf';
        }
        if (str_contains($content, "\n")) {
            return 'lf';
        }
        if (str_contains($content, "\r")) {
            return 'cr';
        }
        return 'none';
    }

    private function siteRoot(string $siteId): string
    {
        $siteId = strtolower(trim($siteId));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/', $siteId) !== 1) {
            throw new \InvalidArgumentException(
                'OPUS_SITE_SOURCE_SITE_ID_INVALID'
            );
        }
        $candidate = realpath($this->sitesRoot . '/' . $siteId);
        $candidate = is_string($candidate)
            ? rtrim(str_replace('\\', '/', $candidate), '/')
            : '';
        if ($candidate === ''
            || !str_starts_with($candidate, $this->sitesRoot . '/')
            || !is_dir($candidate)) {
            throw new \RuntimeException(
                'OPUS_SITE_SOURCE_SITE_ROOT_INVALID'
            );
        }
        $site = $this->loader->read($candidate . '/config/site.json');
        if (($site['contract'] ?? null) !== 'OPUS_SITE_STANDARD_CONTRACT_CORE'
            || strtolower(trim((string) ($site['site_id'] ?? '')))
                !== $siteId) {
            throw new \RuntimeException(
                'OPUS_SITE_SOURCE_SITE_CONTRACT_INVALID'
            );
        }
        return $candidate;
    }

    private function existingTarget(
        string $siteRoot,
        string $relative
    ): string {
        if (!$this->allowed($relative)) {
            throw new \RuntimeException(
                'OPUS_SITE_SOURCE_FILE_NOT_ALLOWED'
            );
        }
        $candidate = realpath($siteRoot . '/' . $relative);
        $candidate = is_string($candidate)
            ? str_replace('\\', '/', $candidate)
            : '';
        if ($candidate === ''
            || !str_starts_with($candidate, $siteRoot . '/')
            || !is_file($candidate)
            || is_link($candidate)) {
            throw new \RuntimeException(
                'OPUS_SITE_SOURCE_FILE_INVALID'
            );
        }
        return $candidate;
    }

    private function relative(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));
        if ($path === ''
            || str_contains($path, "\0")
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:\//', $path) === 1
            || preg_match('/[\x00-\x1F\x7F:]/', $path) === 1) {
            throw new \InvalidArgumentException(
                'OPUS_SITE_SOURCE_PATH_INVALID'
            );
        }
        $path = trim($path, '/');
        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException(
                    'OPUS_SITE_SOURCE_PATH_INVALID'
                );
            }
        }
        return implode('/', $segments);
    }

    private function allowed(string $relative): bool
    {
        try {
            $relative = $this->relative($relative);
        } catch (Throwable) {
            return false;
        }
        $segments = array_map('strtolower', explode('/', $relative));
        foreach ($segments as $segment) {
            if (in_array($segment, self::BLOCKED_SEGMENTS, true)
                || in_array($segment, self::BLOCKED_NAMES, true)
                || str_starts_with($segment, '.env.')) {
                return false;
            }
        }
        $extension = strtolower((string) pathinfo(
            $relative,
            PATHINFO_EXTENSION
        ));
        return in_array($extension, self::ALLOWED_EXTENSIONS, true);
    }

    private function assertText(string $content): void
    {
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $content) === 1) {
            throw new \RuntimeException('OPUS_SITE_SOURCE_BINARY_FORBIDDEN');
        }
    }

    private function hash(string $hash): string
    {
        $hash = strtolower(trim($hash));
        if (preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
            throw new \InvalidArgumentException(
                'OPUS_SITE_SOURCE_HASH_INVALID'
            );
        }
        return $hash;
    }

    private function assertMaxFiles(int $maxFiles): void
    {
        if ($maxFiles < 1 || $maxFiles > self::MAX_FILES) {
            throw new \InvalidArgumentException(
                'OPUS_SITE_SOURCE_MAX_FILES_INVALID'
            );
        }
    }

    private function assertMaxBytes(int $maxBytes): void
    {
        if ($maxBytes < 1 || $maxBytes > self::DEFAULT_MAX_BYTES) {
            throw new \InvalidArgumentException(
                'OPUS_SITE_SOURCE_MAX_BYTES_INVALID'
            );
        }
    }

    /** @return resource */
    private function lock(string $siteRoot, string $relative)
    {
        $directory = $siteRoot . '/var/locks/source';
        if (!is_dir($directory)
            && !mkdir($directory, 0775, true)
            && !is_dir($directory)) {
            throw new \RuntimeException(
                'OPUS_SITE_SOURCE_LOCK_DIRECTORY_FAILED'
            );
        }
        $path = $directory . '/' . hash('sha256', $relative) . '.lock';
        $stream = fopen($path, 'c+b');
        if ($stream === false || !flock($stream, LOCK_EX)) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new \RuntimeException('OPUS_SITE_SOURCE_LOCK_FAILED');
        }
        return $stream;
    }

    /** @param resource $stream */
    private function unlock($stream): void
    {
        flock($stream, LOCK_UN);
        fclose($stream);
    }

    /**
     * @param array<string,mixed> $context
     * @param callable():array<string,mixed> $callback
     * @return array<string,mixed>
     */
    private function observed(
        string $operation,
        array $context,
        callable $callback
    ): array {
        $ownedTrace = false;
        $spanId = null;
        $traceId = null;
        if ($this->profiler !== null) {
            if ($this->profiler->getActiveTrace() === null) {
                $this->profiler->start();
                $ownedTrace = true;
            }
            $traceId = $this->profiler->getActiveTrace()?->getTraceId();
            $spanId = $this->profiler->beginSpan(
                'source',
                'source.' . $operation,
                $this->safeContext($context),
                $this->parentSpanId
            );
        }

        try {
            $result = $callback();
            $summary = [
                'operation' => $operation,
                'status' => 'succeeded',
            ];
            $this->logger?->info(
                'source',
                'OPUS_SITE_SOURCE_OPERATION_SUCCEEDED',
                $this->safeContext([...$context, ...$summary]),
                $traceId
            );
            if ($spanId !== null) {
                $this->profiler?->endSpan(
                    $spanId,
                    'success',
                    $summary
                );
            }
            if ($ownedTrace) {
                $this->profiler?->stop($summary);
            }
            return $result;
        } catch (Throwable $error) {
            $summary = [
                'operation' => $operation,
                'status' => 'failed',
                'error_code' => $this->safeErrorCode($error),
            ];
            $this->logger?->error(
                'source',
                'OPUS_SITE_SOURCE_OPERATION_FAILED',
                $this->safeContext([...$context, ...$summary]),
                $traceId
            );
            if ($spanId !== null) {
                $this->profiler?->endSpan(
                    $spanId,
                    'error',
                    $summary
                );
            }
            if ($ownedTrace) {
                $this->profiler?->stop($summary);
            }
            throw $error;
        }
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    private function safeContext(array $context): array
    {
        $allowed = [
            'application_id', 'path', 'operation', 'status',
            'proposed_bytes', 'file_count', 'error_code',
        ];
        return array_intersect_key($context, array_flip($allowed));
    }

    private function safeErrorCode(Throwable $error): string
    {
        $message = trim($error->getMessage());
        return preg_match('/^[A-Z0-9_:-]{3,240}$/D', $message) === 1
            ? $message
            : 'OPUS_SITE_SOURCE_OPERATION_FAILED';
    }
}
