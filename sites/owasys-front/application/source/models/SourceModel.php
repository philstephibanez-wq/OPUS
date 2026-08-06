<?php
declare(strict_types=1);

use Opus\Api\Rest\RestClient;
use Opus\Api\Rest\RestClientInterface;

/** Secured frontend projection of OPUS source operations through OWASYS REST. */
final class OwasysSourceModel
{
    private const MAX_CONTENT_BYTES = 1048576;

    private readonly RestClientInterface $rest;

    public function __construct(
        string $siteRoot,
        ?RestClientInterface $rest = null
    ) {
        $this->rest = $rest ?? RestClient::fromConfig(
            rtrim(str_replace('\\', '/', $siteRoot), '/')
                . '/config/rest-api.json'
        );
    }

    /** @param array<string,mixed> $actor @return array<string,mixed> */
    public function list(string $siteId, array $actor): array
    {
        $siteId = $this->siteId($siteId);
        $result = $this->rest->request(
            'GET',
            '/api/v1/applications/' . rawurlencode($siteId) . '/sources',
            [],
            $this->actor($actor)
        );
        if (!in_array(
            $result['contract'] ?? null,
            ['OPUS_SITE_SOURCE_LIST_V1', 'OPUS_SITE_SOURCE_LIST_V2'],
            true
        ) || !is_array($result['files'] ?? null)) {
            throw new RuntimeException('OWASYS_SOURCE_LIST_RESULT_INVALID');
        }
        return $result;
    }

    /** @param array<string,mixed> $actor @return array<string,mixed> */
    public function read(
        string $siteId,
        string $path,
        array $actor
    ): array {
        $siteId = $this->siteId($siteId);
        $path = $this->path($path);
        $result = $this->rest->request(
            'GET',
            $this->sourceResource($siteId, $path),
            [],
            $this->actor($actor)
        );
        if (!in_array(
            $result['contract'] ?? null,
            ['OPUS_SITE_SOURCE_FILE_V1', 'OPUS_SITE_SOURCE_FILE_V2'],
            true
        ) || !is_string($result['content'] ?? null)
            || !is_string($result['sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $result['sha256']) !== 1) {
            throw new RuntimeException('OWASYS_SOURCE_READ_RESULT_INVALID');
        }
        return $result;
    }

    /** @param array<string,mixed> $actor @return array<string,mixed> */
    public function browse(
        string $siteId,
        string $path,
        array $actor
    ): array {
        return [
            'contract' => 'OWASYS_SOURCE_BROWSE_V1',
            'listing' => $this->list($siteId, $actor),
            'selected' => $this->read($siteId, $path, $actor),
        ];
    }

    /** @param array<string,mixed> $actor @return array<string,mixed> */
    public function preview(
        string $siteId,
        string $path,
        string $expectedContentHash,
        string $newContent,
        array $actor
    ): array {
        $siteId = $this->siteId($siteId);
        $path = $this->path($path);
        $expectedContentHash = $this->hash($expectedContentHash);
        $newContent = $this->content($newContent);
        $result = $this->rest->request(
            'POST',
            $this->previewResource($siteId, $path),
            [
                'expected_content_hash' => $expectedContentHash,
                'new_content' => $newContent,
            ],
            $this->actor($actor)
        );
        if (($result['contract'] ?? null)
                !== 'OPUS_SITE_SOURCE_PREVIEW_V1'
            || !is_bool($result['changed'] ?? null)
            || !is_string($result['diff'] ?? null)
            || !is_bool($result['diff_truncated'] ?? null)
            || $this->resultPath($result) !== $path
            || $this->resultHash($result, 'current_sha256')
                !== $expectedContentHash) {
            throw new RuntimeException(
                'OWASYS_SOURCE_PREVIEW_RESULT_INVALID'
            );
        }
        return $result;
    }

    /** @param array<string,mixed> $actor @return array<string,mixed> */
    public function write(
        string $siteId,
        string $path,
        string $expectedContentHash,
        string $newContent,
        array $actor
    ): array {
        $siteId = $this->siteId($siteId);
        $path = $this->path($path);
        $expectedContentHash = $this->hash($expectedContentHash);
        $newContent = $this->content($newContent);
        $result = $this->rest->request(
            'PUT',
            $this->sourceResource($siteId, $path),
            [
                'expected_content_hash' => $expectedContentHash,
                'new_content' => $newContent,
            ],
            $this->actor($actor)
        );
        if (($result['contract'] ?? null)
                !== 'OPUS_SITE_SOURCE_WRITE_V1'
            || !is_bool($result['changed'] ?? null)
            || !is_string($result['diff'] ?? null)
            || !is_bool($result['diff_truncated'] ?? null)
            || $this->resultPath($result) !== $path
            || $this->resultHash($result, 'previous_sha256')
                !== $expectedContentHash
            || $this->resultHash($result, 'sha256')
                !== hash('sha256', $newContent)) {
            throw new RuntimeException(
                'OWASYS_SOURCE_WRITE_RESULT_INVALID'
            );
        }
        return $result;
    }

    private function sourceResource(string $siteId, string $path): string
    {
        return '/api/v1/applications/' . rawurlencode($siteId)
            . '/sources/' . $this->resourcePath($path);
    }

    private function previewResource(string $siteId, string $path): string
    {
        return '/api/v1/applications/' . rawurlencode($siteId)
            . '/source-previews/' . $this->resourcePath($path);
    }

    private function resourcePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    private function siteId(string $siteId): string
    {
        $siteId = strtolower(trim($siteId));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $siteId) !== 1) {
            throw new RuntimeException('OWASYS_SOURCE_SITE_ID_INVALID');
        }
        return $siteId;
    }

    private function path(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === ''
            || str_contains($path, "\0")
            || str_contains($path, '..')
            || preg_match('/^[A-Za-z0-9._\/-]{1,512}$/D', $path) !== 1) {
            throw new RuntimeException('OWASYS_SOURCE_PATH_INVALID');
        }
        return $path;
    }

    private function hash(string $hash): string
    {
        $hash = strtolower(trim($hash));
        if (preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
            throw new RuntimeException('OWASYS_SOURCE_HASH_INVALID');
        }
        return $hash;
    }

    private function content(string $content): string
    {
        if (strlen($content) > self::MAX_CONTENT_BYTES
            || str_contains($content, "\0")) {
            throw new RuntimeException('OWASYS_SOURCE_CONTENT_INVALID');
        }
        return $content;
    }

    /** @param array<string,mixed> $result */
    private function resultPath(array $result): string
    {
        return $this->path((string) ($result['path'] ?? ''));
    }

    /** @param array<string,mixed> $result */
    private function resultHash(array $result, string $name): string
    {
        return $this->hash((string) ($result[$name] ?? ''));
    }

    /**
     * @param array<string,mixed> $actor
     * @return array{subject:string,roles:list<string>,provider:string}
     */
    private function actor(array $actor): array
    {
        $subject = trim((string) ($actor['subject'] ?? $actor['id'] ?? ''));
        $roles = is_array($actor['roles'] ?? null)
            ? array_values(array_unique(array_filter(
                $actor['roles'],
                'is_string'
            )))
            : [];
        $provider = trim((string) ($actor['provider'] ?? ''));
        if ($subject === '' || $roles === [] || $provider === '') {
            throw new RuntimeException('OWASYS_SOURCE_ACTOR_INVALID');
        }
        return [
            'subject' => $subject,
            'roles' => $roles,
            'provider' => $provider,
        ];
    }
}
