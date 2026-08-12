<?php
declare(strict_types=1);

namespace Opus\Security\Sso;

use Opus\File\File;
use Opus\File\FileInterface;
use Opus\File\Json;
use Opus\File\JsonInterface;
use Opus\File\StructuredFileLoader;
use Opus\File\StructuredFileLoaderInterface;

/**
 * Provisions an initial local-password credential in a non-versioned runtime store.
 *
 * Generated applications keep their onboarding-owned roles. Standard OPUS
 * applications require explicit roles, each validated against their deny-by-default
 * ACL policy. Clear-text credentials exist only in caller memory.
 */
final class LocalPasswordCredentialProvisioner implements
    LocalPasswordCredentialProvisionerInterface
{
    private const SITE_CONTRACT = 'OPUS_SITE_STANDARD_CONTRACT_CORE';
    private const GENERATED_SSO_CONTRACT =
        'OPUS_GENERATED_APPLICATION_SSO_V1';
    private const STANDARD_SSO_CONTRACT =
        'OPUS_SSO_CONFIGURATION_V1';
    private const ONBOARDING_CONTRACT =
        'OPUS_SECURITY_ONBOARDING_V1';
    private const GENERATED_STORE_CONTRACT =
        'OPUS_LOCAL_USER_STORE_V1';
    private const ACL_CONTRACT = 'OPUS_ACL_POLICY_V1';

    private readonly string $opusRoot;
    private readonly FileInterface $file;
    private readonly JsonInterface $json;
    private readonly StructuredFileLoaderInterface $loader;

    public function __construct(
        string $opusRoot,
        ?FileInterface $file = null,
        ?JsonInterface $json = null,
        ?StructuredFileLoaderInterface $loader = null
    ) {
        $root = rtrim(str_replace('\\', '/', $opusRoot), '/');
        if ($root === '' || !is_dir($root . '/sites')) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_ROOT_INVALID'
            );
        }

        $this->opusRoot = $root;
        $this->file = $file ?? File::instance();
        $this->json = $json ?? Json::instance();
        $this->loader = $loader ?? StructuredFileLoader::instance();
    }

    public function provision(
        string $siteId,
        string $subject,
        string $password,
        array $roles = []
    ): array {
        $siteId = strtolower(trim($siteId));
        $subject = trim($subject);
        $roles = $this->normalizeRoles($roles);

        if (preg_match(
            '/^[a-z0-9][a-z0-9_-]{1,63}$/D',
            $siteId
        ) !== 1) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_SITE_ID_INVALID'
            );
        }

        if (preg_match(
            '/^[A-Za-z0-9][A-Za-z0-9._@-]{0,127}$/D',
            $subject
        ) !== 1) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_SUBJECT_INVALID'
            );
        }

        $siteRoot = $this->opusRoot . '/sites/' . $siteId;
        if (!is_dir($siteRoot)) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_SITE_UNKNOWN'
            );
        }

        $site = $this->config(
            $siteRoot . '/config/site.json',
            [self::SITE_CONTRACT => true]
        );
        $minimum = max(
            8,
            (int) ($site['auth']['minimum_password_length'] ?? 10)
        );
        if (strlen($password) < $minimum) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_PASSWORD_TOO_SHORT'
            );
        }

        $siteRole = trim((string) ($site['role'] ?? ''));
        if (!in_array(
            $siteRole,
            ['generated-opus-application', 'standard-opus-application'],
            true
        )) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_SITE_ROLE_INVALID'
            );
        }

        if ($siteRole === 'generated-opus-application') {
            if (($site['generated_by'] ?? null) !== 'composer') {
                throw new \RuntimeException(
                    'OPUS_LOCAL_PASSWORD_PROVISION_SITE_NOT_GENERATED'
                );
            }
            if ($roles !== []) {
                throw new \RuntimeException(
                    'OPUS_LOCAL_PASSWORD_PROVISION_ROLE_OVERRIDE_FORBIDDEN'
                );
            }

            return $this->provisionGenerated(
                $siteRoot,
                $siteId,
                $subject,
                $password
            );
        }

        if ($roles === []) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_ROLE_REQUIRED'
            );
        }

        return $this->provisionStandard(
            $siteRoot,
            $siteId,
            $subject,
            $password,
            $roles
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function provisionGenerated(
        string $siteRoot,
        string $siteId,
        string $subject,
        string $password
    ): array {
        $sso = $this->config(
            $siteRoot . '/config/sso.json',
            [self::GENERATED_SSO_CONTRACT => true]
        );
        $provider = $this->localPasswordProvider($sso);

        $onboarding = $this->config(
            $siteRoot . '/config/security.onboarding.json',
            [self::ONBOARDING_CONTRACT => true]
        );
        if (($onboarding['provider'] ?? null) !== 'local-password') {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_ONBOARDING_PROVIDER_INVALID'
            );
        }

        $identity = $this->onboardedIdentity(
            $onboarding,
            $subject
        );
        $roles = $this->normalizeRoles(
            is_array($identity['roles'] ?? null)
                ? $identity['roles']
                : []
        );
        if ($roles === []) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_ROLES_REQUIRED'
            );
        }

        $providerStore = $this->runtimeStoreRelative(
            (string) ($provider['runtime_store'] ?? '')
        );
        $onboardingStore = $this->runtimeStoreRelative(
            (string) ($onboarding['runtime_store'] ?? '')
        );
        if (!hash_equals($providerStore, $onboardingStore)) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_STORE_MISMATCH'
            );
        }

        return $this->persist(
            $siteRoot,
            $siteId,
            $subject,
            $password,
            $roles,
            $providerStore,
            self::GENERATED_STORE_CONTRACT,
            'generated-opus-application'
        );
    }

    /**
     * @param list<string> $roles
     * @return array<string,mixed>
     */
    private function provisionStandard(
        string $siteRoot,
        string $siteId,
        string $subject,
        string $password,
        array $roles
    ): array {
        $sso = $this->config(
            $siteRoot . '/config/sso.json',
            [self::STANDARD_SSO_CONTRACT => true]
        );
        $provider = $this->localPasswordProvider($sso);

        $acl = $this->config(
            $siteRoot . '/config/acl.json',
            [self::ACL_CONTRACT => true]
        );
        if (($acl['default'] ?? null) !== 'deny'
            || !is_array($acl['roles'] ?? null)) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_ACL_INVALID'
            );
        }

        foreach ($roles as $role) {
            if (!array_key_exists($role, $acl['roles'])) {
                throw new \RuntimeException(
                    'OPUS_LOCAL_PASSWORD_PROVISION_ROLE_UNKNOWN:'
                    . $role
                );
            }
        }

        $store = $this->runtimeStoreRelative(
            (string) (
                $provider['store']
                ?? $provider['runtime_store']
                ?? ''
            )
        );
        $storeContract = trim((string) (
            $provider['store_contract'] ?? ''
        ));
        if (preg_match(
            '/^[A-Z][A-Z0-9_]{2,127}_V[0-9]+$/D',
            $storeContract
        ) !== 1) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_STORE_CONTRACT_INVALID'
            );
        }

        return $this->persist(
            $siteRoot,
            $siteId,
            $subject,
            $password,
            $roles,
            $store,
            $storeContract,
            'standard-opus-application'
        );
    }

    /**
     * @param list<string> $roles
     * @return array<string,mixed>
     */
    private function persist(
        string $siteRoot,
        string $siteId,
        string $subject,
        string $password,
        array $roles,
        string $storeRelative,
        string $storeContract,
        string $siteRole
    ): array {
        $storePath = $siteRoot . '/' . $storeRelative;
        $store = $this->readStore(
            $storePath,
            $storeContract
        );
        $users = is_array($store['users'] ?? null)
            ? $store['users']
            : [];
        $existing = $users[$subject] ?? null;

        if (is_array($existing)
            && trim((string) (
                $existing['password_hash'] ?? ''
            )) !== '') {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_ALREADY_EXISTS'
            );
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_HASH_FAILED'
            );
        }

        $now = gmdate('c');
        $users[$subject] = [
            'id' => $subject,
            'label' => $subject,
            'roles' => $roles,
            'password_hash' => $hash,
            'must_change_password' => false,
            'created_at' => is_array($existing)
                ? (string) ($existing['created_at'] ?? $now)
                : $now,
            'updated_at' => $now,
        ];

        $store['contract'] = $storeContract;
        $store['users'] = $users;
        $store['updated_at'] = $now;

        $this->file->writeAtomic(
            $storePath,
            $this->json->encode($store, true)
        );

        return [
            'contract' => 'OPUS_LOCAL_PASSWORD_PROVISION_RESULT_V2',
            'status' => 'provisioned',
            'site_id' => $siteId,
            'site_role' => $siteRole,
            'subject' => $subject,
            'roles' => $roles,
            'runtime_store' => $storeRelative,
            'store_contract' => $storeContract,
        ];
    }

    /**
     * @param array<string,mixed> $sso
     * @return array<string,mixed>
     */
    private function localPasswordProvider(array $sso): array
    {
        $providers = is_array($sso['providers'] ?? null)
            ? $sso['providers']
            : [];
        $provider = is_array(
            $providers['local-password'] ?? null
        )
            ? $providers['local-password']
            : [];

        if (($sso['default_provider'] ?? null)
                !== 'local-password'
            || ($provider['enabled'] ?? false) !== true) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_PROVIDER_INACTIVE'
            );
        }

        return $provider;
    }

    /**
     * @param array<string,true> $contracts
     * @return array<string,mixed>
     */
    private function config(
        string $path,
        array $contracts
    ): array {
        $data = $this->loader->read($path);
        $contract = (string) ($data['contract'] ?? '');
        if (!isset($contracts[$contract])) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_CONFIG_INVALID'
            );
        }

        return $data;
    }

    /**
     * @param array<string,mixed> $onboarding
     * @return array<string,mixed>
     */
    private function onboardedIdentity(
        array $onboarding,
        string $subject
    ): array {
        foreach (
            (array) ($onboarding['identities'] ?? [])
            as $identity
        ) {
            if (!is_array($identity)
                || trim((string) (
                    $identity['subject'] ?? ''
                )) !== $subject) {
                continue;
            }

            $status = trim((string) (
                $identity['status'] ?? ''
            ));
            if ($status !== 'password-setup-required') {
                throw new \RuntimeException(
                    'OPUS_LOCAL_PASSWORD_PROVISION_ONBOARDING_STATUS_INVALID'
                );
            }

            return $identity;
        }

        throw new \RuntimeException(
            'OPUS_LOCAL_PASSWORD_PROVISION_SUBJECT_NOT_ONBOARDED'
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function readStore(
        string $path,
        string $contract
    ): array {
        if (!$this->file->exists($path)) {
            return [
                'contract' => $contract,
                'users' => [],
            ];
        }

        $store = $this->json->parse(
            $this->file->read($path),
            $path
        );
        if (($store['contract'] ?? null) !== $contract) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_STORE_CONTRACT_INVALID'
            );
        }

        $store['users'] = is_array($store['users'] ?? null)
            ? $store['users']
            : [];

        return $store;
    }

    /**
     * @param array<mixed> $roles
     * @return list<string>
     */
    private function normalizeRoles(array $roles): array
    {
        $normalized = [];

        foreach ($roles as $role) {
            if (!is_string($role)) {
                throw new \RuntimeException(
                    'OPUS_LOCAL_PASSWORD_PROVISION_ROLE_INVALID'
                );
            }

            $role = strtolower(trim($role));
            if (preg_match(
                '/^[a-z][a-z0-9_-]{0,63}$/D',
                $role
            ) !== 1) {
                throw new \RuntimeException(
                    'OPUS_LOCAL_PASSWORD_PROVISION_ROLE_INVALID'
                );
            }

            $normalized[$role] = true;
        }

        return array_keys($normalized);
    }

    private function runtimeStoreRelative(
        string $path
    ): string {
        $path = trim(str_replace('\\', '/', $path), '/');
        if (!str_starts_with($path, 'var/auth/')
            || str_contains($path, '..')
            || str_contains($path, "\0")
            || !str_ends_with($path, '.json')) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_STORE_PATH_INVALID'
            );
        }

        return $path;
    }
}
