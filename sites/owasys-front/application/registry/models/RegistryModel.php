<?php
declare(strict_types=1);

use Opus\Rcp\Rest\RcpRestClient;
use Opus\Rcp\Rest\RcpRestClientInterface;

/** Read-only frontend projection of the Registry through secured REST. */
final class OwasysRegistryModel
{
    private readonly RcpRestClientInterface $rcp;
    private readonly OwasysAuthSession $session;

    /** @var array<string,mixed>|null */
    private ?array $snapshot = null;

    public function __construct(string $siteRoot)
    {
        $this->rcp = RcpRestClient::fromConfig(
            rtrim(str_replace('\\', '/', $siteRoot), '/') . '/config/rcp.json'
        );
        $this->session = new OwasysAuthSession();
    }

    /** @return array<string,mixed> */
    public function synchronize(): array
    {
        $result = $this->rcp->execute(
            'registry.sync',
            [],
            $this->sessionActor()
        );
        $snapshot = $result['snapshot'] ?? null;
        if (!is_array($snapshot)
            || !is_array($snapshot['sync'] ?? null)
            || !is_array($snapshot['entries'] ?? null)
            || !is_array($snapshot['recent_events'] ?? null)) {
            throw new RuntimeException('OWASYS_REGISTRY_SNAPSHOT_INVALID');
        }
        $this->snapshot = $snapshot;
        return $snapshot['sync'];
    }

    /** @return list<array<string,mixed>> */
    public function entries(): array
    {
        return array_values(array_filter(
            $this->snapshot()['entries'],
            'is_array'
        ));
    }

    /** @return list<array<string,mixed>> */
    public function recentEvents(int $limit = 8): array
    {
        if ($limit < 1 || $limit > 50) {
            throw new RuntimeException('OWASYS_RUNTIME_EVENT_LIMIT_INVALID');
        }
        return array_slice(
            array_values(array_filter(
                $this->snapshot()['recent_events'],
                'is_array'
            )),
            0,
            $limit
        );
    }

    /** @return array<string,mixed>|null */
    public function find(string $applicationId): ?array
    {
        foreach ($this->entries() as $entry) {
            if ((string) ($entry['id'] ?? '') === $applicationId) {
                return $entry;
            }
        }
        return null;
    }

    /** @param array<string,mixed>|null $current */
    public function canonicalCurrent(?array $current): ?array
    {
        if (!is_array($current)) {
            return null;
        }
        $applicationId = trim((string) ($current['id'] ?? ''));
        return $applicationId === '' ? null : $this->find($applicationId);
    }

    /** @param array<string,mixed> $application @param array<string,mixed> $actor */
    public function setCurrent(array $application, array $actor): void
    {
        $applicationId = trim((string) ($application['id'] ?? ''));
        if ($applicationId === '') {
            throw new RuntimeException('OWASYS_REGISTRY_APPLICATION_ID_MISSING');
        }
        $this->rcp->execute(
            'registry.select',
            ['application_id' => $applicationId],
            $this->actor($actor)
        );
        $this->snapshot = null;
    }

    /** @param array<string,mixed> $actor */
    public function clear(array $actor): void
    {
        $this->rcp->execute('registry.clear', [], $this->actor($actor));
        $this->snapshot = null;
    }

    /** @param array<string,mixed> $actor @return array<string,mixed> */
    public function delete(
        string $applicationId,
        string $confirmation,
        array $actor
    ): array {
        $result = $this->rcp->execute(
            'site.delete',
            [
                'site_id' => $applicationId,
                'confirmation' => $confirmation,
                'write' => true,
            ],
            $this->actor($actor)
        );
        if (($result['contract'] ?? null)
            !== 'OPUS_CONSOLE_SITE_DELETE_RESULT_V1'
            || ($result['deleted'] ?? null) !== true
            || ($result['site_id'] ?? null) !== $applicationId) {
            throw new RuntimeException('OWASYS_SITE_DELETE_RESULT_INVALID');
        }
        $this->snapshot = null;
        return $result;
    }

    /** @return array<string,mixed> */
    private function snapshot(): array
    {
        if (!is_array($this->snapshot)) {
            $this->synchronize();
        }
        return $this->snapshot ?? throw new RuntimeException(
            'OWASYS_REGISTRY_SNAPSHOT_UNAVAILABLE'
        );
    }

    /** @return array{subject:string,roles:list<string>,provider:string} */
    private function sessionActor(): array
    {
        $identity = $this->session->user();
        if (!is_array($identity)) {
            throw new RuntimeException('OWASYS_REGISTRY_AUTH_REQUIRED');
        }
        return $this->actor($identity);
    }

    /** @param array<string,mixed> $actor @return array{subject:string,roles:list<string>,provider:string} */
    private function actor(array $actor): array
    {
        $subject = trim((string) ($actor['subject'] ?? $actor['id'] ?? ''));
        $roles = is_array($actor['roles'] ?? null)
            ? array_values(array_unique(array_filter($actor['roles'], 'is_string')))
            : [];
        $provider = trim((string) ($actor['provider'] ?? ''));
        if ($subject === '' || $roles === [] || $provider === '') {
            throw new RuntimeException('OWASYS_REGISTRY_ACTOR_INVALID');
        }
        return [
            'subject' => $subject,
            'roles' => $roles,
            'provider' => $provider,
        ];
    }
}
