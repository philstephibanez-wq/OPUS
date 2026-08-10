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
 * Provisions an initial local-password credential for a generated OPUS site.
 *
 * The clear-text credential exists only in caller memory. Only password_hash()
 * output is persisted, under the generated site's non-versioned var/auth store.
 */
final class LocalPasswordCredentialProvisioner implements
    LocalPasswordCredentialProvisionerInterface
{
    private const SITE_CONTRACT = 'OPUS_SITE_STANDARD_CONTRACT_CORE';
    private const SSO_CONTRACT = 'OPUS_GENERATED_APPLICATION_SSO_V1';
    private const ONBOARDING_CONTRACT = 'OPUS_SECURITY_ONBOARDING_V1';
    private const STORE_CONTRACT = 'OPUS_LOCAL_USER_STORE_V1';

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
        string $password
    ): array {
        $siteId = trim($siteId);
        $subject = trim($subject);
        if (preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/', $siteId) !== 1) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_SITE_ID_INVALID'
            );
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._@-]{0,127}$/', $subject) !== 1) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_SUBJECT_INVALID'
            );
        }
        if (strlen($password) < 10) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_PASSWORD_TOO_SHORT'
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
            self::SITE_CONTRACT
        );
        if (($site['role'] ?? null) !== 'generated-opus-application'
            || ($site['generated_by'] ?? null) !== 'composer') {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_SITE_NOT_GENERATED'
            );
        }

        $sso = $this->config(
            $siteRoot . '/config/sso.json',
            self::SSO_CONTRACT
        );
        $providers = is_array($sso['providers'] ?? null)
            ? $sso['providers']
            : [];
        $provider = is_array($providers['local-password'] ?? null)
            ? $providers['local-password']
            : [];
        if (($sso['default_provider'] ?? null) !== 'local-password'
            || ($provider['enabled'] ?? false) !== true) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_PROVIDER_INACTIVE'
            );
        }

        $onboarding = $this->config(
            $siteRoot . '/config/security.onboarding.json',
            self::ONBOARDING_CONTRACT
        );
        if (($onboarding['provider'] ?? null) !== 'local-password') {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_ONBOARDING_PROVIDER_INVALID'
            );
        }

        $identity = $this->onboardedIdentity($onboarding, $subject);
        $providerStore = $this->runtimeStoreRelative((string) (
            $provider['runtime_store'] ?? ''
        ));
        $onboardingStore = $this->runtimeStoreRelative((string) (
            $onboarding['runtime_store'] ?? ''
        ));
        if ($providerStore !== $onboardingStore) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_STORE_MISMATCH'
            );
        }

        $storePath = $siteRoot . '/' . $providerStore;
        $store = $this->readStore($storePath);
        $users = is_array($store['users'] ?? null) ? $store['users'] : [];
        $existing = $users[$subject] ?? null;
        if (is_array($existing)
            && trim((string) ($existing['password_hash'] ?? '')) !== '') {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_ALREADY_EXISTS'
            );
        }

        $roles = is_array($identity['roles'] ?? null)
            ? array_values(array_unique(array_values(array_filter(
                $identity['roles'],
                static fn (mixed $role): bool => is_string($role)
                    && trim($role) !== ''
            ))))
            : [];
        if ($roles === []) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_ROLES_REQUIRED'
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
        $store['contract'] = self::STORE_CONTRACT;
        $store['users'] = $users;
        $store['updated_at'] = $now;

        $this->file->writeAtomic(
            $storePath,
            $this->json->encode($store, true)
        );

        return [
            'contract' => 'OPUS_LOCAL_PASSWORD_PROVISION_RESULT_V1',
            'status' => 'provisioned',
            'site_id' => $siteId,
            'subject' => $subject,
            'runtime_store' => $providerStore,
        ];
    }

    /** @return array<string,mixed> */
    private function config(string $path, string $contract): array
    {
        $data = $this->loader->read($path);
        if (($data['contract'] ?? null) !== $contract) {
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
    private function onboardedIdentity(array $onboarding, string $subject): array
    {
        foreach ((array) ($onboarding['identities'] ?? []) as $identity) {
            if (!is_array($identity)
                || trim((string) ($identity['subject'] ?? '')) !== $subject) {
                continue;
            }
            $status = trim((string) ($identity['status'] ?? ''));
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

    /** @return array<string,mixed> */
    private function readStore(string $path): array
    {
        if (!$this->file->exists($path)) {
            return [
                'contract' => self::STORE_CONTRACT,
                'users' => [],
            ];
        }
        $store = $this->json->parse($this->file->read($path), $path);
        if (($store['contract'] ?? null) !== self::STORE_CONTRACT) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_PROVISION_STORE_CONTRACT_INVALID'
            );
        }
        $store['users'] = is_array($store['users'] ?? null)
            ? $store['users']
            : [];
        return $store;
    }

    private function runtimeStoreRelative(string $path): string
    {
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
