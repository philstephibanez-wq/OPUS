<?php
declare(strict_types=1);

use Opus\Rcp\Rest\RcpRestClient;
use Opus\Rcp\Rest\RcpRestClientInterface;

/** OWASYS frontend projection for OPUS application creation through REST + Composer. */
final class OwasysApplicationCreationModel
{
    private readonly RcpRestClientInterface $rcp;

    public function __construct(
        string $siteRoot,
        private readonly OwasysRegistryModel $registry,
        ?RcpRestClientInterface $rcp = null
    ) {
        $this->rcp = $rcp ?? RcpRestClient::fromConfig(
            rtrim(str_replace('\\', '/', $siteRoot), '/') . '/config/rcp.json'
        );
    }

    /**
     * @param array<string,mixed> $actor
     * @return array{application:array<string,mixed>,command:array<string,mixed>}
     */
    public function create(
        string $siteId,
        string $profile,
        array $actor
    ): array {
        $siteId = strtolower(trim($siteId));
        if (preg_match('/^[a-z][a-z0-9-]{0,63}$/', $siteId) !== 1) {
            throw new RuntimeException('OWASYS_CREATION_SITE_ID_INVALID');
        }
        $profile = strtolower(trim($profile));
        if (!in_array($profile, ['frontend', 'backend', 'fullstack'], true)) {
            throw new RuntimeException('OWASYS_CREATION_PROFILE_INVALID');
        }

        $command = $this->rcp->execute(
            'site.create',
            [
                'site_id' => $siteId,
                'profile' => $profile,
                'write' => true,
            ],
            $this->actor($actor)
        );
        if (($command['written'] ?? false) !== true
            || (string) ($command['site_id'] ?? '') !== $siteId
            || (string) ($command['profile'] ?? '') !== $profile) {
            throw new RuntimeException('OWASYS_CREATION_COMMAND_RESULT_INVALID');
        }

        $this->registry->synchronize();
        $application = $this->registry->find($siteId);
        if (!is_array($application)) {
            throw new RuntimeException('OWASYS_CREATION_REGISTRY_ENTRY_MISSING');
        }

        return [
            'application' => $application,
            'command' => $command,
        ];
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
            throw new RuntimeException('OWASYS_CREATION_ACTOR_INVALID');
        }
        return [
            'subject' => $subject,
            'roles' => $roles,
            'provider' => $provider,
        ];
    }
}
