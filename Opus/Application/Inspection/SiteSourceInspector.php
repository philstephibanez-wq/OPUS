<?php
declare(strict_types=1);

namespace Opus\Application\Inspection;

use Opus\Application\Source\SiteSourceWorkspace;
use Opus\Application\Source\SiteSourceWorkspaceInterface;

/** Read-only compatibility facade over the generic OPUS source workspace. */
final class SiteSourceInspector implements SiteSourceInspectorInterface
{
    public const CONTRACT = 'OPUS_SITE_SOURCE_INSPECTOR_V1';

    private readonly SiteSourceWorkspaceInterface $workspace;

    public function __construct(string $opusRoot)
    {
        $this->workspace = new SiteSourceWorkspace($opusRoot);
    }

    public function list(
        string $siteId,
        int $maxFiles = 5000,
        int $maxBytes = 1048576
    ): array {
        $listing = $this->workspace->list(
            $siteId,
            $maxFiles,
            $maxBytes
        );
        $files = [];
        foreach ((array) ($listing['files'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $files[] = [
                'path' => (string) ($entry['path'] ?? ''),
                'bytes' => (int) ($entry['bytes'] ?? 0),
            ];
        }

        return [
            'contract' => 'OPUS_SITE_SOURCE_LIST_V1',
            'application_id' => strtolower(trim($siteId)),
            'read_only' => true,
            'truncated' => ($listing['truncated'] ?? false) === true,
            'files' => $files,
        ];
    }

    public function read(
        string $siteId,
        string $relativePath,
        int $maxBytes = 1048576
    ): array {
        $file = $this->workspace->read(
            $siteId,
            $relativePath,
            $maxBytes
        );

        return [
            'contract' => 'OPUS_SITE_SOURCE_FILE_V1',
            'application_id' => strtolower(trim($siteId)),
            'path' => (string) ($file['path'] ?? ''),
            'bytes' => (int) ($file['bytes'] ?? 0),
            'sha256' => (string) ($file['sha256'] ?? ''),
            'content' => (string) ($file['content'] ?? ''),
            'read_only' => true,
        ];
    }
}
