<?php
declare(strict_types=1);

namespace Opus\Application\Inspection;

use Opus\File\File;
use Opus\File\StructuredFileLoader;

/** Read-only, bounded and secret-aware inspection of a standard OPUS site. */
final class SiteSourceInspector implements SiteSourceInspectorInterface
{
    public const CONTRACT = 'OPUS_SITE_SOURCE_INSPECTOR_V1';

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

    private readonly string $opusRoot;
    private readonly string $sitesRoot;
    private readonly File $files;
    private readonly StructuredFileLoader $loader;

    public function __construct(string $opusRoot)
    {
        $realRoot = realpath($opusRoot);
        if (!is_string($realRoot) || !is_dir($realRoot)) {
            throw new \RuntimeException('OPUS_SITE_SOURCE_OPUS_ROOT_INVALID');
        }
        $sitesRoot = realpath($realRoot . '/sites');
        if (!is_string($sitesRoot) || !is_dir($sitesRoot)) {
            throw new \RuntimeException('OPUS_SITE_SOURCE_SITES_ROOT_INVALID');
        }
        $this->opusRoot = rtrim(str_replace('\\', '/', $realRoot), '/');
        $this->sitesRoot = rtrim(str_replace('\\', '/', $sitesRoot), '/');
        $this->files = File::instance();
        $this->loader = StructuredFileLoader::instance();
    }

    public function list(
        string $siteId,
        int $maxFiles = 5000,
        int $maxBytes = 1048576
    ): array {
        if ($maxFiles < 1 || $maxFiles > 5000) {
            throw new \InvalidArgumentException('OPUS_SITE_SOURCE_MAX_FILES_INVALID');
        }
        if ($maxBytes < 1 || $maxBytes > 1048576) {
            throw new \InvalidArgumentException('OPUS_SITE_SOURCE_MAX_BYTES_INVALID');
        }

        $siteRoot = $this->siteRoot($siteId);
        $result = [];
        $truncated = false;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $siteRoot,
                \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $candidate) {
            if (!$candidate instanceof \SplFileInfo
                || !$candidate->isFile()
                || $candidate->isLink()) {
                continue;
            }
            $pathname = str_replace('\\', '/', $candidate->getPathname());
            if (!str_starts_with($pathname, $siteRoot . '/')) {
                continue;
            }
            $relative = substr($pathname, strlen($siteRoot) + 1);
            if (!$this->allowed($relative)) {
                continue;
            }
            $size = $candidate->getSize();
            if ($size < 0 || $size > $maxBytes) {
                continue;
            }
            $result[] = [
                'path' => $relative,
                'bytes' => $size,
            ];
            if (count($result) >= $maxFiles) {
                $truncated = !$iterator->valid() ? false : true;
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
            'contract' => 'OPUS_SITE_SOURCE_LIST_V1',
            'application_id' => $siteId,
            'read_only' => true,
            'truncated' => $truncated,
            'files' => $result,
        ];
    }

    public function read(
        string $siteId,
        string $relativePath,
        int $maxBytes = 1048576
    ): array {
        if ($maxBytes < 1 || $maxBytes > 1048576) {
            throw new \InvalidArgumentException('OPUS_SITE_SOURCE_MAX_BYTES_INVALID');
        }
        $siteRoot = $this->siteRoot($siteId);
        $relative = $this->relative($relativePath);
        if (!$this->allowed($relative)) {
            throw new \RuntimeException('OPUS_SITE_SOURCE_FILE_NOT_ALLOWED');
        }
        $candidate = realpath($siteRoot . '/' . $relative);
        $candidate = is_string($candidate)
            ? str_replace('\\', '/', $candidate)
            : '';
        if ($candidate === ''
            || !str_starts_with($candidate, $siteRoot . '/')
            || !is_file($candidate)
            || is_link($candidate)) {
            throw new \RuntimeException('OPUS_SITE_SOURCE_FILE_INVALID');
        }
        $content = $this->files->read($candidate, $maxBytes);

        return [
            'contract' => 'OPUS_SITE_SOURCE_FILE_V1',
            'application_id' => $siteId,
            'path' => $relative,
            'bytes' => strlen($content),
            'sha256' => hash('sha256', $content),
            'content' => $content,
            'read_only' => true,
        ];
    }

    private function siteRoot(string $siteId): string
    {
        $siteId = strtolower(trim($siteId));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/', $siteId) !== 1) {
            throw new \InvalidArgumentException('OPUS_SITE_SOURCE_SITE_ID_INVALID');
        }
        $candidate = realpath($this->sitesRoot . '/' . $siteId);
        $candidate = is_string($candidate)
            ? rtrim(str_replace('\\', '/', $candidate), '/')
            : '';
        if ($candidate === ''
            || !str_starts_with($candidate, $this->sitesRoot . '/')
            || !is_dir($candidate)) {
            throw new \RuntimeException('OPUS_SITE_SOURCE_SITE_ROOT_INVALID');
        }
        $configFile = $candidate . '/config/site.json';
        $site = $this->loader->read($configFile);
        if (($site['contract'] ?? null) !== 'OPUS_SITE_STANDARD_CONTRACT_CORE'
            || strtolower(trim((string) ($site['site_id'] ?? ''))) !== $siteId) {
            throw new \RuntimeException('OPUS_SITE_SOURCE_SITE_CONTRACT_INVALID');
        }
        return $candidate;
    }

    private function relative(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === ''
            || str_contains($path, "\0")
            || str_contains($path, '..')
            || str_starts_with($path, '/')) {
            throw new \InvalidArgumentException('OPUS_SITE_SOURCE_PATH_INVALID');
        }
        return $path;
    }

    private function allowed(string $relative): bool
    {
        try {
            $relative = $this->relative($relative);
        } catch (\Throwable) {
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
        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        return in_array($extension, self::ALLOWED_EXTENSIONS, true);
    }
}
