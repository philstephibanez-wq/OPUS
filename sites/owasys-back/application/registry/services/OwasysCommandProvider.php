<?php
declare(strict_types=1);

use Opus\Console\Application\ApplicationCommandProviderInterface;
use Opus\Database\DatabaseOperationProfiler;
use Opus\File\File;
use Opus\File\StructuredFileLoader;
use Opus\Profiler\Profiler;
use Opus\Security\Acl\AclPolicy;
use Opus\Security\Sso\LocalPasswordSsoProvider;
use Opus\Security\Sso\SsoManager;

/** Application-owned Composer command provider for OWASYS business operations. */
final class OwasysCommandProvider implements OwasysCommandProviderInterface
{
    private const COMMANDS = [
        'owasys:registry:sync' => true,
        'owasys:registry:select' => true,
        'owasys:registry:clear' => true,
        'owasys:security:admin-password:change' => true,
        'owasys:security:snapshot' => true,
    ];

    private readonly AclPolicy $acl;
    private readonly Profiler $profiler;
    private ?string $activeComposerSpanId = null;

    public function __construct(
        private readonly string $siteRoot,
        private readonly string $opusRoot
    ) {
        $this->acl = new AclPolicy($this->siteRoot . '/config/acl.json');
        $this->profiler = new Profiler(
            $this->siteRoot . '/var/profiler/runtime'
        );
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
        $traceId = strtolower(trim((string) ($request['trace_id'] ?? '')));
        if (preg_match('/^[a-f0-9]{16,64}$/D', $traceId) !== 1) {
            throw new RuntimeException('OWASYS_COMMAND_TRACE_ID_INVALID');
        }
        $ownsTrace = $this->profiler->getActiveTrace() === null;
        if ($ownsTrace) {
            $this->profiler->start($traceId);
        }
        $spanId = $this->profiler->beginSpan(
            'composer',
            'composer.command',
            ['command' => $command]
        );
        $this->activeComposerSpanId = $spanId;

        try {
            $result = match ($command) {
                'owasys:registry:sync' => $this->registrySnapshot($actor),
                'owasys:registry:select' => $this->registrySelect(
                    $arguments,
                    $actor
                ),
                'owasys:registry:clear' => $this->registryClear($actor),
                'owasys:security:admin-password:change' =>
                    $this->changePassword($request, $actor),
                'owasys:security:snapshot' => $this->securitySnapshot(
                    $arguments,
                    $actor
                ),
                default => throw new RuntimeException(
                    'OWASYS_COMMAND_UNKNOWN:' . $command
                ),
            };
            $this->profiler->endSpan($spanId, 'success');
            return $result;
        } catch (Throwable $error) {
            $this->profiler->endSpan($spanId, 'error', [
                'exception_class' => $error::class,
            ]);
            throw $error;
        } finally {
            $this->activeComposerSpanId = null;
            if ($ownsTrace) {
                $this->profiler->stop([
                    'component' => self::class,
                    'command' => $command,
                ]);
            }
        }
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
        $entriesById = [];
        foreach ($repository->entries() as $entry) {
            $entryId = trim((string) ($entry['id'] ?? ''));
            if ($entryId === '') {
                continue;
            }
            $entriesById[$entryId] = array_replace($entry, [
                'singleton' => $inspector->inspect(
                    (string) ($entry['root_path'] ?? '')
                ),
            ]);
        }
        $standardEntries = $this->standardApplicationEntries();
        foreach ($standardEntries as $entry) {
            $entryId = (string) $entry['id'];
            $entriesById[$entryId] = array_replace($entry, [
                'singleton' => $inspector->inspect(
                    (string) $entry['root_path']
                ),
            ]);
        }
        ksort($entriesById, SORT_STRING);
        $entries = array_values($entriesById);
        $sync['total'] = count($entries);
        $sync['standard_discovered'] = count($standardEntries);

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
        $this->assertAllowed($actor, 'registry', 'select');
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
            foreach ($this->standardApplicationEntries() as $entry) {
                if ((string) ($entry['id'] ?? '') === $applicationId) {
                    $selected = $entry;
                    break;
                }
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
        $this->assertAllowed($actor, 'registry', 'select');
        $cleared = $this->repository()->clearCurrentApplication(
            (string) $actor['subject']
        );
        return [
            'contract' => 'OWASYS_REGISTRY_CLEAR_COMMAND_RESULT_V2',
            'cleared' => $cleared,
            'already_empty' => !$cleared,
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

    /**
     * @param list<string> $arguments
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    private function securitySnapshot(
        array $arguments,
        array $actor
    ): array {
        $this->assertAllowed($actor, 'security', 'read');
        $siteId = strtolower(trim((string) ($arguments[0] ?? '')));
        if (count($arguments) !== 1
            || preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $siteId) !== 1) {
            throw new RuntimeException(
                'OWASYS_SECURITY_SNAPSHOT_SITE_ID_INVALID'
            );
        }

        $targetRoot = rtrim(str_replace('\\', '/', $this->opusRoot), '/')
            . '/sites/'
            . $siteId;
        $file = File::instance();
        $loader = StructuredFileLoader::instance();
        $required = [
            'site' => $targetRoot . '/config/site.json',
            'acl' => $targetRoot . '/config/acl.json',
            'sso' => $targetRoot . '/config/sso.json',
        ];
        foreach ($required as $id => $path) {
            if (!$file->exists($path)) {
                throw new RuntimeException(
                    'OWASYS_SECURITY_CONFIG_MISSING:' . $id
                );
            }
        }

        $site = $loader->read($required['site']);
        if (($site['contract'] ?? null)
            !== 'OPUS_SITE_STANDARD_CONTRACT_CORE') {
            throw new RuntimeException(
                'OWASYS_SECURITY_SITE_CONTRACT_INVALID'
            );
        }
        if (strtolower(trim((string) ($site['site_id'] ?? '')))
            !== $siteId) {
            throw new RuntimeException(
                'OWASYS_SECURITY_SITE_ID_MISMATCH'
            );
        }

        $acl = $loader->read($required['acl']);
        $aclContract = (string) ($acl['contract'] ?? '');
        if (!in_array(
            $aclContract,
            ['OPUS_ACL_POLICY_V1', 'OPUS_GENERATED_APPLICATION_ACL_V1'],
            true
        )) {
            throw new RuntimeException(
                'OWASYS_SECURITY_ACL_CONTRACT_INVALID:' . $aclContract
            );
        }

        $sso = $loader->read($required['sso']);
        $ssoContract = (string) ($sso['contract'] ?? '');
        if (!in_array(
            $ssoContract,
            ['OPUS_SSO_CONFIGURATION_V1', 'OPUS_GENERATED_APPLICATION_SSO_V1'],
            true
        )) {
            throw new RuntimeException(
                'OWASYS_SECURITY_SSO_CONTRACT_INVALID:' . $ssoContract
            );
        }

        $onboarding = null;
        $onboardingFile = $targetRoot
            . '/config/security.onboarding.json';
        if ($file->exists($onboardingFile)) {
            $onboarding = $loader->read($onboardingFile);
            if (($onboarding['contract'] ?? null)
                !== 'OPUS_SECURITY_ONBOARDING_V1') {
                throw new RuntimeException(
                    'OWASYS_SECURITY_ONBOARDING_CONTRACT_INVALID'
                );
            }
        }

        $permissions = $this->securityPermissions(
            $acl,
            $aclContract
        );
        $identities = $this->securityIdentities(
            $targetRoot,
            $sso,
            $ssoContract,
            $onboarding
        );
        $assignments = [];
        foreach ($identities as $identity) {
            foreach ((array) ($identity['roles'] ?? []) as $role) {
                if (!is_string($role) || trim($role) === '') {
                    continue;
                }
                $assignments[] = [
                    'subject' => (string) ($identity['subject'] ?? ''),
                    'role' => trim($role),
                    'scope_type' => 'application',
                    'scope_id' => $siteId,
                    'source' => (string) ($identity['source'] ?? ''),
                ];
            }
        }

        $authenticationRequired = array_key_exists(
            'authentication_required',
            $sso
        ) && is_bool($sso['authentication_required'])
            ? $sso['authentication_required']
            : null;

        return [
            'contract' => 'OWASYS_SECURITY_SNAPSHOT_V1',
            'application' => [
                'id' => $siteId,
                'name' => (string) (
                    $site['site_name'] ?? $siteId
                ),
                'kind' => (string) (
                    $site['application_profile']['type']
                    ?? $site['kind']
                    ?? ''
                ),
                'root' => 'sites/' . $siteId,
            ],
            'overview' => [
                'acl_contract' => $aclContract,
                'default_policy' => (string) ($acl['default'] ?? 'deny'),
                'sso_contract' => $ssoContract,
                'authentication_required' => $authenticationRequired,
                'default_provider' => (string) (
                    $sso['default_provider'] ?? ''
                ),
                'onboarding_present' => is_array($onboarding),
            ],
            'providers' => $this->securityProviders($sso),
            'identities' => $identities,
            'roles' => $this->securityRoles($acl, $aclContract),
            'permissions' => $permissions,
            'assignments' => $assignments,
            'resources' => $this->securityResources(
                $acl,
                $aclContract,
                $permissions
            ),
        ];
    }

    /**
     * @param array<string,mixed> $acl
     * @return list<array{id:string,resource:string,action:string}>
     */
    private function securityPermissions(
        array $acl,
        string $contract
    ): array {
        $ids = [];
        if ($contract === 'OPUS_ACL_POLICY_V1') {
            foreach ((array) ($acl['roles'] ?? []) as $grants) {
                if (!is_array($grants)) {
                    continue;
                }
                foreach ($grants as $grant) {
                    if (is_string($grant) && trim($grant) !== '') {
                        $ids[trim($grant)] = true;
                    }
                }
            }
        } else {
            foreach ((array) ($acl['permissions'] ?? []) as $permission) {
                if (is_string($permission) && trim($permission) !== '') {
                    $ids[trim($permission)] = true;
                }
            }
        }
        ksort($ids, SORT_STRING);
        $result = [];
        foreach (array_keys($ids) as $id) {
            [$resource, $action] = $this->permissionParts($id);
            $result[] = [
                'id' => $id,
                'resource' => $resource,
                'action' => $action,
            ];
        }
        return $result;
    }

    /**
     * @param array<string,mixed> $acl
     * @return list<array<string,mixed>>
     */
    private function securityRoles(array $acl, string $contract): array
    {
        $result = [];
        if ($contract === 'OPUS_ACL_POLICY_V1') {
            foreach ((array) ($acl['roles'] ?? []) as $role => $grants) {
                if (!is_string($role) || !is_array($grants)) {
                    continue;
                }
                $permissions = array_values(array_unique(array_filter(
                    $grants,
                    'is_string'
                )));
                sort($permissions, SORT_STRING);
                $result[] = [
                    'id' => $role,
                    'permissions' => $permissions,
                    'permissions_count' => count($permissions),
                    'assignment_known' => true,
                ];
            }
        } else {
            foreach ((array) ($acl['roles'] ?? []) as $role) {
                if (!is_string($role) || trim($role) === '') {
                    continue;
                }
                $result[] = [
                    'id' => trim($role),
                    'permissions' => [],
                    'permissions_count' => 0,
                    'assignment_known' => false,
                ];
            }
        }
        usort(
            $result,
            static fn (array $left, array $right): int => strcmp(
                (string) $left['id'],
                (string) $right['id']
            )
        );
        return $result;
    }

    /**
     * @param array<string,mixed> $acl
     * @param list<array{id:string,resource:string,action:string}> $permissions
     * @return list<array<string,mixed>>
     */
    private function securityResources(
        array $acl,
        string $contract,
        array $permissions
    ): array {
        $map = [];
        if ($contract === 'OPUS_ACL_POLICY_V1') {
            foreach ((array) ($acl['roles'] ?? []) as $role => $grants) {
                if (!is_string($role) || !is_array($grants)) {
                    continue;
                }
                foreach ($grants as $grant) {
                    if (!is_string($grant) || trim($grant) === '') {
                        continue;
                    }
                    [$resource, $action] = $this->permissionParts($grant);
                    $key = $resource . ':' . $action;
                    $map[$key] ??= [
                        'resource' => $resource,
                        'action' => $action,
                        'allowed_roles' => [],
                        'source' => 'acl.role-grant',
                    ];
                    $map[$key]['allowed_roles'][$role] = true;
                }
            }
        } else {
            foreach ((array) ($acl['policies'] ?? []) as $policyId => $policy) {
                if (!is_string($policyId) || !is_array($policy)) {
                    continue;
                }
                [$resource, $action] = str_contains($policyId, ':')
                    ? $this->permissionParts($policyId)
                    : [$policyId, 'open'];
                $key = $resource . ':' . $action;
                $map[$key] = [
                    'resource' => $resource,
                    'action' => $action,
                    'allowed_roles' => array_fill_keys(
                        array_values(array_filter(
                            is_array($policy['roles'] ?? null)
                                ? $policy['roles']
                                : [],
                            'is_string'
                        )),
                        true
                    ),
                    'source' => 'acl.policy',
                ];
            }
            foreach ($permissions as $permission) {
                $key = $permission['resource']
                    . ':'
                    . $permission['action'];
                $map[$key] ??= [
                    'resource' => $permission['resource'],
                    'action' => $permission['action'],
                    'allowed_roles' => [],
                    'source' => 'acl.permission-unassigned',
                ];
            }
        }

        ksort($map, SORT_STRING);
        $result = [];
        foreach ($map as $row) {
            $allowed = is_array($row['allowed_roles'] ?? null)
                ? array_keys($row['allowed_roles'])
                : [];
            sort($allowed, SORT_STRING);
            $row['allowed_roles'] = $allowed;
            $result[] = $row;
        }
        return $result;
    }

    /** @param array<string,mixed> $sso @return list<array{id:string,enabled:bool}> */
    private function securityProviders(array $sso): array
    {
        $result = [];
        foreach ((array) ($sso['providers'] ?? []) as $id => $config) {
            if (!is_string($id) || !is_array($config)) {
                continue;
            }
            $result[] = [
                'id' => $id,
                'enabled' => ($config['enabled'] ?? false) === true,
            ];
        }
        usort(
            $result,
            static fn (array $left, array $right): int => strcmp(
                (string) $left['id'],
                (string) $right['id']
            )
        );
        return $result;
    }

    /**
     * @param array<string,mixed> $sso
     * @param array<string,mixed>|null $onboarding
     * @return list<array<string,mixed>>
     */
    private function securityIdentities(
        string $targetRoot,
        array $sso,
        string $ssoContract,
        ?array $onboarding
    ): array {
        $identities = [];
        if (is_array($onboarding)) {
            $provider = trim((string) ($onboarding['provider'] ?? ''));
            foreach ((array) ($onboarding['identities'] ?? []) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $subject = trim((string) ($entry['subject'] ?? ''));
                if ($subject === '') {
                    continue;
                }
                $identities[$provider . ':' . $subject] = [
                    'provider' => $provider,
                    'subject' => $subject,
                    'label' => $subject,
                    'status' => (string) ($entry['status'] ?? 'active'),
                    'roles' => array_values(array_filter(
                        is_array($entry['roles'] ?? null)
                            ? $entry['roles']
                            : [],
                        'is_string'
                    )),
                    'must_change_password' =>
                        (string) ($entry['status'] ?? '')
                            === 'password-setup-required',
                    'source' => 'security.onboarding',
                ];
            }
        }

        $providers = is_array($sso['providers'] ?? null)
            ? $sso['providers']
            : [];
        $local = is_array($providers['local-password'] ?? null)
            ? $providers['local-password']
            : [];
        if (($local['enabled'] ?? false) !== true) {
            ksort($identities, SORT_STRING);
            return array_values($identities);
        }

        $onboardingStore = is_array($onboarding)
            ? (string) ($onboarding['runtime_store'] ?? '')
            : '';
        $storeRelative = trim((string) (
            $local['store']
            ?? $local['runtime_store']
            ?? $onboardingStore
        ));
        if ($storeRelative === '') {
            throw new RuntimeException(
                'OWASYS_SECURITY_LOCAL_STORE_PATH_MISSING'
            );
        }
        $storeRelative = $this->safeRelative($storeRelative);
        $storeFile = $targetRoot . '/' . $storeRelative;
        $file = File::instance();
        if (!$file->exists($storeFile)) {
            ksort($identities, SORT_STRING);
            return array_values($identities);
        }

        $store = StructuredFileLoader::instance()->read($storeFile);
        $expectedContract = trim((string) (
            $local['store_contract']
            ?? ($ssoContract === 'OPUS_GENERATED_APPLICATION_SSO_V1'
                ? 'OPUS_LOCAL_USER_STORE_V1'
                : '')
        ));
        if ($expectedContract === ''
            || ($store['contract'] ?? null) !== $expectedContract) {
            throw new RuntimeException(
                'OWASYS_SECURITY_LOCAL_STORE_CONTRACT_INVALID'
            );
        }

        foreach ((array) ($store['users'] ?? []) as $username => $entry) {
            if (!is_string($username) || !is_array($entry)) {
                continue;
            }
            $subject = trim((string) ($entry['id'] ?? $username));
            if ($subject === '') {
                continue;
            }
            $roles = is_array($entry['roles'] ?? null)
                ? array_values(array_filter($entry['roles'], 'is_string'))
                : [];
            if ($roles === []) {
                $profile = trim((string) ($entry['profile'] ?? ''));
                if ($profile !== '') {
                    $roles = [$profile];
                }
            }
            $identities['local-password:' . $subject] = [
                'provider' => 'local-password',
                'subject' => $subject,
                'label' => (string) ($entry['label'] ?? $username),
                'status' => (string) ($entry['status'] ?? 'active'),
                'roles' => $roles,
                'must_change_password' =>
                    ($entry['must_change_password'] ?? false) === true,
                'source' => 'runtime.local-password',
            ];
        }
        ksort($identities, SORT_STRING);
        return array_values($identities);
    }

    /** @return array{0:string,1:string} */
    private function permissionParts(string $permission): array
    {
        $permission = trim($permission);
        $separator = strpos($permission, ':');
        if ($separator === false) {
            return [$permission, 'open'];
        }
        return [
            substr($permission, 0, $separator),
            substr($permission, $separator + 1),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function standardApplicationEntries(): array
    {
        $file = File::instance();
        $loader = StructuredFileLoader::instance();
        $entries = [];
        $pattern = rtrim(str_replace('\\', '/', $this->opusRoot), '/')
            . '/sites/*/config/site.json';

        foreach ($file->matching($pattern) as $configFile) {
            $site = $loader->read($configFile);
            if (($site['contract'] ?? null)
                !== 'OPUS_SITE_STANDARD_CONTRACT_CORE') {
                continue;
            }
            $siteRoot = dirname(dirname(str_replace('\\', '/', $configFile)));
            $directoryId = basename($siteRoot);
            $siteId = strtolower(trim((string) ($site['site_id'] ?? '')));
            if ($siteId === ''
                || $siteId !== strtolower($directoryId)
                || preg_match('/^[a-z][a-z0-9-]*$/', $siteId) !== 1) {
                throw new RuntimeException(
                    'OWASYS_STANDARD_APPLICATION_ID_INVALID:' . $directoryId
                );
            }
            $profile = is_array($site['application_profile'] ?? null)
                ? $site['application_profile']
                : [];
            $kind = strtolower(trim((string) (
                $profile['type'] ?? $site['kind'] ?? 'fullstack'
            )));
            if (!in_array($kind, ['frontend', 'backend', 'fullstack'], true)) {
                throw new RuntimeException(
                    'OWASYS_STANDARD_APPLICATION_PROFILE_INVALID:' . $siteId
                );
            }
            if ($profile !== []
                && ($profile['contract'] ?? null)
                    !== 'OPUS_APPLICATION_PROFILE_V1') {
                throw new RuntimeException(
                    'OWASYS_STANDARD_APPLICATION_PROFILE_CONTRACT_INVALID:'
                    . $siteId
                );
            }
            $entries[] = [
                'id' => $siteId,
                'slug' => (string) ($site['slug'] ?? $siteId),
                'name' => (string) ($site['site_name'] ?? $siteId),
                'kind' => $kind,
                'root_path' => 'sites/' . $directoryId,
                'public_root' => (string) ($site['public_root'] ?? 'www'),
                'default_locale' => (string) ($site['default_locale'] ?? 'fr'),
                'theme' => (string) ($site['theme'] ?? 'default'),
                'status' => (string) ($site['status'] ?? 'discovered'),
                'blueprint' => (string) (
                    $site['blueprint'] ?? ('opus-' . $kind)
                ),
                'generated_by' => (string) (
                    $site['generated_by'] ?? 'opus-composer'
                ),
                'role' => (string) (
                    $site['role'] ?? 'standard-opus-application'
                ),
                'source' => 'standard-discovery',
            ];
        }

        usort(
            $entries,
            static fn (array $left, array $right): int => strcmp(
                (string) $left['id'],
                (string) $right['id']
            )
        );
        return $entries;
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
            !== 'OPUS_REST_API_COMPOSER_COMMAND_REQUEST_V1') {
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
            $this->opusRoot,
            null,
            new DatabaseOperationProfiler(
                $this->profiler,
                $this->activeComposerSpanId
            )
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
