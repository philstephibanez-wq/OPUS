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
 * Resets an existing local-password credential in a non-versioned runtime store.
 *
 * Supports generated and standard OPUS applications whose enabled default SSO
 * provider is local-password. Clear-text credentials remain caller-memory only.
 */
final class LocalPasswordCredentialResetter implements
    LocalPasswordCredentialResetterInterface
{
    private const SITE_CONTRACT = 'OPUS_SITE_STANDARD_CONTRACT_CORE';

    /** @var array<string,true> */
    private const SSO_CONTRACTS = [
        'OPUS_GENERATED_APPLICATION_SSO_V1' => true,
        'OPUS_SSO_CONFIGURATION_V1' => true,
    ];

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
                'OPUS_LOCAL_PASSWORD_RESET_ROOT_INVALID'
            );
        }

        $this->opusRoot = $root;
        $this->file = $file ?? File::instance();
        $this->json = $json ?? Json::instance();
        $this->loader = $loader ?? StructuredFileLoader::instance();
    }

    public function reset(
        string $siteId,
        string $subject,
        string $password,
        bool $mustChangePassword = false
    ): array {
        $siteId = strtolower(trim($siteId));
        $subject = trim($subject);

        if (preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/D', $siteId) !== 1) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_SITE_ID_INVALID'
            );
        }
        if (preg_match(
            '/^[A-Za-z0-9][A-Za-z0-9._@-]{0,127}$/D',
            $subject
        ) !== 1) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_SUBJECT_INVALID'
            );
        }

        $siteRoot = $this->opusRoot . '/sites/' . $siteId;
        if (!is_dir($siteRoot)) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_SITE_UNKNOWN'
            );
        }

        $site = $this->config(
            $siteRoot . '/config/site.json',
            [self::SITE_CONTRACT => true]
        );
        $role = trim((string) ($site['role'] ?? ''));
        if (!in_array(
            $role,
            ['generated-opus-application', 'standard-opus-application'],
            true
        )) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_SITE_ROLE_INVALID'
            );
        }
        if ($role === 'generated-opus-application'
            && ($site['generated_by'] ?? null) !== 'composer') {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_SITE_NOT_GENERATED'
            );
        }

        $minimum = max(
            8,
            (int) ($site['auth']['minimum_password_length'] ?? 10)
        );
        if (strlen($password) < $minimum) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_PASSWORD_TOO_SHORT'
            );
        }

        $sso = $this->config(
            $siteRoot . '/config/sso.json',
            self::SSO_CONTRACTS
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
                'OPUS_LOCAL_PASSWORD_RESET_PROVIDER_INACTIVE'
            );
        }

        $storeRelative = $this->runtimeStoreRelative(
            (string) (
                $provider['runtime_store']
                ?? $provider['store']
                ?? ''
            )
        );
        $storePath = $siteRoot . '/' . $storeRelative;
        if (!$this->file->exists($storePath)) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_STORE_MISSING'
            );
        }

        $store = $this->json->parse(
            $this->file->read($storePath),
            $storePath
        );
        $actualStoreContract = trim((string) ($store['contract'] ?? ''));
        $configuredStoreContract = trim((string) (
            $provider['store_contract'] ?? ''
        ));
        if ($configuredStoreContract !== ''
            && !hash_equals(
                $configuredStoreContract,
                $actualStoreContract
            )) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_STORE_CONTRACT_MISMATCH'
            );
        }
        if (preg_match(
            '/^[A-Z][A-Z0-9_]{2,127}_V[0-9]+$/D',
            $actualStoreContract
        ) !== 1) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_STORE_CONTRACT_INVALID'
            );
        }

        $users = is_array($store['users'] ?? null)
            ? $store['users']
            : [];
        [$username, $existing] = $this->existingUser(
            $users,
            $subject
        );

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_HASH_FAILED'
            );
        }

        $now = gmdate('c');
        $existing['password_hash'] = $hash;
        $existing['must_change_password'] = $mustChangePassword;
        $existing['password_changed_at'] = $now;
        $existing['updated_at'] = $now;
        $users[$username] = $existing;

        $store['users'] = $users;
        $store['updated_at'] = $now;

        $this->file->writeAtomic(
            $storePath,
            $this->json->encode($store, true)
        );

        return [
            'contract' => 'OPUS_LOCAL_PASSWORD_RESET_RESULT_V1',
            'status' => 'reset',
            'site_id' => $siteId,
            'subject' => (string) ($existing['id'] ?? $username),
            'runtime_store' => $storeRelative,
            'store_contract' => $actualStoreContract,
            'must_change_password' => $mustChangePassword,
        ];
    }

    /**
     * @param array<string,true> $contracts
     * @return array<string,mixed>
     */
    private function config(string $path, array $contracts): array
    {
        $data = $this->loader->read($path);
        $contract = (string) ($data['contract'] ?? '');
        if (!isset($contracts[$contract])) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_CONFIG_INVALID'
            );
        }

        return $data;
    }

    /**
     * @param array<string,mixed> $users
     * @return array{0:string,1:array<string,mixed>}
     */
    private function existingUser(array $users, string $subject): array
    {
        foreach ($users as $username => $candidate) {
            if (!is_string($username) || !is_array($candidate)) {
                continue;
            }
            if ($username === $subject
                || (string) ($candidate['id'] ?? '') === $subject) {
                return [$username, $candidate];
            }
        }

        throw new \RuntimeException(
            'OPUS_LOCAL_PASSWORD_RESET_SUBJECT_UNKNOWN'
        );
    }

    private function runtimeStoreRelative(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        if (!str_starts_with($path, 'var/auth/')
            || str_contains($path, '..')
            || str_contains($path, "\0")
            || !str_ends_with($path, '.json')) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_STORE_PATH_INVALID'
            );
        }

        return $path;
    }
}
