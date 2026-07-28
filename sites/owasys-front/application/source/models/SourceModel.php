<?php
declare(strict_types=1);

use Opus\Rcp\Rest\RcpRestClient;
use Opus\Rcp\Rest\RcpRestClientInterface;

/** Read-only frontend projection of OPUS application source through secured REST. */
final class OwasysSourceModel
{
    private readonly RcpRestClientInterface $rcp;

    public function __construct(
        string $siteRoot,
        ?RcpRestClientInterface $rcp = null
    ) {
        $this->rcp = $rcp ?? RcpRestClient::fromConfig(
            rtrim(str_replace('\\', '/', $siteRoot), '/') . '/config/rcp.json'
        );
    }

    /** @param array<string,mixed> $actor @return array<string,mixed> */
    public function list(string $siteId, array $actor): array
    {
        $result = $this->rcp->execute(
            'source.list',
            ['site_id' => $this->siteId($siteId)],
            $this->actor($actor)
        );
        if (($result['contract'] ?? null) !== 'OPUS_SITE_SOURCE_LIST_V1'
            || !is_array($result['files'] ?? null)) {
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
        $result = $this->rcp->execute(
            'source.read',
            [
                'site_id' => $this->siteId($siteId),
                'path' => $this->path($path),
            ],
            $this->actor($actor)
        );
        if (($result['contract'] ?? null) !== 'OPUS_SITE_SOURCE_FILE_V1'
            || !is_string($result['content'] ?? null)) {
            throw new RuntimeException('OWASYS_SOURCE_READ_RESULT_INVALID');
        }
        return $result;
    }

    private function siteId(string $siteId): string
    {
        $siteId = strtolower(trim($siteId));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/', $siteId) !== 1) {
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
            || preg_match('/^[A-Za-z0-9._\/-]{1,512}$/', $path) !== 1) {
            throw new RuntimeException('OWASYS_SOURCE_PATH_INVALID');
        }
        return $path;
    }

    /** @param array<string,mixed> $actor @return array{subject:string,roles:list<string>,provider:string} */
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
