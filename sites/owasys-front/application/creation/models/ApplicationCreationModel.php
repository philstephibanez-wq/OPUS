<?php
declare(strict_types=1);

use Opus\Api\Rest\RestClient;
use Opus\Api\Rest\RestClientInterface;
use Opus\File\Json;

/** OWASYS frontend projection for OPUS application creation through REST + Composer. */
final class OwasysApplicationCreationModel
{
    private readonly RestClientInterface $rest;

    public function __construct(
        string $siteRoot,
        private readonly OwasysRegistryModel $registry,
        ?RestClientInterface $rest = null
    ) {
        $this->rest = $rest ?? RestClient::fromConfig(
            rtrim(str_replace('\\', '/', $siteRoot), '/') . '/config/rest-api.json'
        );
    }

    /**
     * @param array<string,mixed> $actor
     * @return array{application:array<string,mixed>,command:array<string,mixed>}
     */
    public function create(
        string $siteId,
        string $profile,
        array $blueprint,
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
        if (($blueprint['contract'] ?? null)
            !== 'OPUS_SITE_CREATION_BLUEPRINT_V1') {
            throw new RuntimeException(
                'OWASYS_CREATION_BLUEPRINT_CONTRACT_INVALID'
            );
        }
        $encodedBlueprint = Json::instance()->encode($blueprint, false);
        if (strlen($encodedBlueprint) > 8192) {
            throw new RuntimeException(
                'OWASYS_CREATION_BLUEPRINT_TOO_LARGE'
            );
        }

        $command = $this->rest->request(
            'POST',
            '/api/v1/applications',
            [
                'site_id' => $siteId,
                'profile' => $profile,
                'blueprint' => $encodedBlueprint,
            ],
            $this->actor($actor)
        );
        if (($command['written'] ?? false) !== true
            || (string) ($command['site_id'] ?? '') !== $siteId
            || (string) ($command['profile'] ?? '') !== $profile) {
            throw new RuntimeException('OWASYS_CREATION_COMMAND_RESULT_INVALID');
        }

        try {
            $this->registry->synchronize();
            $application = $this->registry->find($siteId);
            if (!is_array($application)) {
                throw new RuntimeException(
                    'OWASYS_CREATION_REGISTRY_ENTRY_MISSING'
                );
            }
        } catch (Throwable $error) {
            try {
                $rollback = $this->rest->request(
                    'DELETE',
                    '/api/v1/applications/' . rawurlencode($siteId),
                    ['confirmation' => $siteId],
                    $this->actor($actor)
                );
                if (($rollback['deleted'] ?? false) !== true) {
                    throw new RuntimeException(
                        'OWASYS_CREATION_ROLLBACK_RESULT_INVALID'
                    );
                }
            } catch (Throwable $rollbackError) {
                throw new RuntimeException(
                    'OWASYS_CREATION_ROLLBACK_FAILED',
                    0,
                    $rollbackError
                );
            }
            throw $error;
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
