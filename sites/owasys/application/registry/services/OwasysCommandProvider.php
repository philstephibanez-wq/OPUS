<?php
declare(strict_types=1);

use Opus\Console\Application\ApplicationCommandProviderInterface;
use Opus\File\StructuredFileLoader;
use Opus\Security\Acl\AclPolicy;
use Opus\Security\Sso\LocalPasswordSsoProvider;
use Opus\Security\Sso\SsoManager;

/** Application-owned Composer command provider for OWASYS business mutations. */
final class OwasysCommandProvider implements OwasysCommandProviderInterface
{
    private const COMMANDS = [
        'owasys:registry:sync' => true,
        'owasys:registry:select' => true,
        'owasys:registry:clear' => true,
        'owasys:registry:creation:start' => true,
        'owasys:security:admin-password:change' => true,
    ];

    private readonly AclPolicy $acl;

    public function __construct(
        private readonly string $siteRoot,
        private readonly string $opusRoot
    ) {
        $this->acl = new AclPolicy($this->siteRoot . '/config/acl.json');
    }

    public function supports(string $command): bool
    {
        return isset(self::COMMANDS[$command]);
    }

    public function execute(
        string $command,
        array $arguments,
        array $request
    ): array {
        if (!$this->supports($command)) {
            throw new RuntimeException(
                'OWASYS_COMMAND_UNKNOWN:' . $command
            );
        }

        $actor = $this->actor($request);

        return match ($command) {
            'owasys:registry:sync' => $this->registrySnapshot($actor),
            'owasys:registry:select' => $this->registrySelect(
                $arguments,
                $actor
            ),
            'owasys:registry:clear' => $this->registryClear($actor),
            'owasys:registry:creation:start' =>
                $this->registryStartCreation($actor),
            'owasys:security:admin-password:change' =>
                $this->changePassword($request, $actor),
            default => throw new RuntimeException(
                'OWASYS_COMMAND_UNKNOWN:' . $command
            ),
        };
    }

    /** @param array<string,mixed> $actor */
    private function registrySnapshot(array $actor): array
    {
        $this->assertAllowed($actor, 'registry', 'open');
        $repository = $this->repository();
        $sync = $repository->synchronize(
            $this->siteRoot . '/config/registry.seed.json'
        );
        $inspector = OwasysApplicationSingletonInspector::instance(
            $this->opusRoot
        );
        $entries = [];
        foreach ($repository->entries() as $entry) {
            $entries[] = array_replace($entry, [
                'singleton' => $inspector->inspect(
                    (string) ($entry['root_path'] ?? '')
                ),
            ]);
        }

        return [
            'contract' => 'OWASYS_REGISTRY_SYNC_COMMAND_RESULT_V2',
            'snapshot' => [
                'contract' => 'OWASYS_REGISTRY_REST_SNAPSHOT_V1',
                'sync' => $sync,
                'entries' => $entries,
                'recent_events' => $repository->recentEvents(8),
            ],
        ];
    }

    /** @param list<string> $arguments @param array<string,mixed> $actor */
    private function registrySelect(
        array $arguments,
        array $actor
    ): array {
        $this->assertAllowed($actor, 'registry', 'write');
        $applicationId = trim((string) ($arguments[0] ?? ''));
        if (preg_match('/^[a-z][a-z0-9-]*$/', $applicationId) !== 1) {
            throw new RuntimeException(
                'OWASYS_REGISTRY_APPLICATION_ID_INVALID'
            );
        }

        $repository = $this->repository();
        $selected = null;
        foreach ($repository->entries() as $entry) {
            if ((string) ($entry['id'] ?? '') === $applicationId) {
                $selected = $entry;
                break;
            }
        }
        if (!is_array($selected)) {
            throw new RuntimeException(
                'OWASYS_REGISTRY_APPLICATION_NOT_FOUND'
            );
        }

        $repository->setCurrentApplication(
            $selected,
            (string) $actor['subject']
        );

        return [
            'contract' => 'OWASYS_REGISTRY_SELECT_COMMAND_RESULT_V1',
            'application' => $selected,
        ];
    }

    /** @param array<string,mixed> $actor */
    private function registryClear(array $actor): array
    {
        $this->assertAllowed($actor, 'registry', 'write');
        $this->repository()->clearCurrentApplication(
            (string) $actor['subject']
        );
        return [
            'contract' => 'OWASYS_REGISTRY_CLEAR_COMMAND_RESULT_V1',
            'cleared' => true,
        ];
    }

    /** @param array<string,mixed> $actor */
    private function registryStartCreation(array $actor): array
    {
        $this->assertAllowed($actor, 'registry', 'write');
        $this->repository()->startCreationFlow(
            (string) $actor['subject']
        );
        return [
            'contract' => 'OWASYS_REGISTRY_CREATION_COMMAND_RESULT_V1',
            'started' => true,
        ];
    }

    /** @param array<string,mixed> $request @param array<string,mixed> $actor */
    private function changePassword(
        array $request,
        array $actor
    ): array {
        $this->assertAllowed($actor, 'account', 'change');
        $parameters = is_array($request['parameters'] ?? null)
            ? $request['parameters']
            : [];
        $currentPassword = (string) (
            $parameters['current_password'] ?? ''
        );
        $newPassword = (string) ($parameters['new_password'] ?? '');
        if ($currentPassword === '' || $newPassword === '') {
            throw new RuntimeException('OWASYS_CREDENTIALS_INVALID');
        }

        $loader = StructuredFileLoader::instance();
        $site = $loader->read($this->siteRoot . '/config/site.json');
        $sso = $loader->read($this->siteRoot . '/config/sso.json');
        $providerId = trim((string) ($actor['provider'] ?? ''));
        $defaultProvider = trim((string) (
            $sso['default_provider'] ?? ''
        ));
        if ($providerId !== 'local-password'
            || $defaultProvider !== 'local-password') {
            throw new RuntimeException(
                'OWASYS_SECURITY_PROVIDER_UNSUPPORTED'
            );
        }

        $providerConfig = $sso['providers'][$providerId] ?? null;
        if (!is_array($providerConfig)
            || ($providerConfig['enabled'] ?? false) !== true) {
            throw new RuntimeException(
                'OWASYS_SECURITY_PROVIDER_DISABLED'
            );
        }

        $store = $this->safeRelative(
            (string) ($providerConfig['store'] ?? '')
        );
        $minimum = max(
            8,
            (int) ($site['auth']['minimum_password_length'] ?? 10)
        );
        $manager = new SsoManager([
            new LocalPasswordSsoProvider(
                $this->siteRoot . '/' . $store,
                $minimum,
                (string) ($providerConfig['store_contract'] ?? '')
            ),
        ]);
        $identity = $manager->changePassword(
            $providerId,
            (string) $actor['subject'],
            $currentPassword,
            $newPassword
        );
        unset($currentPassword, $newPassword, $parameters, $request);

        return [
            'contract' => 'OWASYS_ADMIN_PASSWORD_CHANGE_RESULT_V1',
            'identity' => $identity->toSession(),
            'audit' => [
                'event' => 'security.admin-password.changed',
                'actor' => $identity->subject,
                'secret_logged' => false,
            ],
        ];
    }

    /** @param array<string,mixed> $actor */
    private function assertAllowed(
        array $actor,
        string $resource,
        string $action
    ): void {
        $decision = $this->acl->decide(
            (array) ($actor['roles'] ?? []),
            $resource,
            $action
        );
        if (!$decision->allowed) {
            throw new RuntimeException('OWASYS_COMMAND_ACL_DENIED');
        }
    }

    /** @param array<string,mixed> $request */
    private function actor(array $request): array
    {
        if (($request['contract'] ?? null)
            !== 'OPUS_RCP_COMPOSER_COMMAND_REQUEST_V1') {
            throw new RuntimeException(
                'OWASYS_COMMAND_REQUEST_CONTRACT_INVALID'
            );
        }
        $actor = is_array($request['actor'] ?? null)
            ? $request['actor']
            : [];
        $subject = trim((string) ($actor['subject'] ?? ''));
        $roles = is_array($actor['roles'] ?? null)
            ? array_values(array_unique(array_filter(
                $actor['roles'],
                'is_string'
            )))
            : [];
        $provider = trim((string) ($actor['provider'] ?? ''));
        if ($subject === '' || $roles === [] || $provider === '') {
            throw new RuntimeException('OWASYS_COMMAND_ACTOR_INVALID');
        }
        return [
            'subject' => $subject,
            'roles' => $roles,
            'provider' => $provider,
        ];
    }

    private function repository(): OwasysRegistryRepository
    {
        return OwasysRegistryRepository::forSite(
            $this->siteRoot,
            $this->opusRoot
        );
    }

    private function safeRelative(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if ($path === ''
            || str_contains($path, '..')
            || preg_match('/^[A-Za-z]:\//', $path) === 1) {
            throw new RuntimeException('OWASYS_COMMAND_PATH_INVALID');
        }
        return $path;
    }
}
