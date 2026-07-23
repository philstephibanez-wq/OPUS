<?php
declare(strict_types=1);

use Opus\Rcp\Rest\RcpRestClient;
use Opus\Rcp\Rest\RcpRestClientInterface;

/**
 * OWASYS Registry REST projection.
 *
 * The frontend opens no Registry repository and performs no persistent write.
 * Every snapshot or mutation is delegated to the secured REST/Composer backend.
 */
final class OwasysRegistryModel
{
    private readonly RcpRestClientInterface $rcp;
    private readonly OwasysAuthSession $session;

    /** @var array<string,mixed>|null */
    private ?array $snapshot = null;

    public function __construct(
        private readonly string $siteRoot,
        string $opusRoot,
        private readonly OwasysApplicationSingletonInspector $singletonInspector,
        string $databaseRelative = 'var/registry/owasys.sqlite',
        string $seedRelative = 'config/registry.seed.json'
    ) {
        $this->rcp = RcpRestClient::fromConfig(
            rtrim(str_replace('\\', '/', $siteRoot), '/')
            . '/config/rcp.json'
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
            throw new RuntimeException(
                'OWASYS_RUNTIME_EVENT_LIMIT_INVALID:' . $limit
            );
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
            throw new RuntimeException(
                'OWASYS_REGISTRY_APPLICATION_ID_MISSING'
            );
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
        $this->rcp->execute(
            'registry.clear',
            [],
            $this->actor($actor)
        );
        $this->snapshot = null;
    }

    /** @param array<string,mixed> $actor */
    public function startCreation(array $actor): void
    {
        $this->rcp->execute(
            'registry.creation.start',
            [],
            $this->actor($actor)
        );
        $this->snapshot = null;
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

    /** @param array<string,mixed> $actor */
    private function actor(array $actor): array
    {
        $subject = trim((string) (
            $actor['subject'] ?? $actor['id'] ?? ''
        ));
        $roles = is_array($actor['roles'] ?? null)
            ? array_values(array_unique(array_filter(
                $actor['roles'],
                'is_string'
            )))
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
