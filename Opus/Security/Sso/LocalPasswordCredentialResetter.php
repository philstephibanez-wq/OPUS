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
 * Resets an existing local-password credential for a generated OPUS site.
 *
 * The clear-text credential exists only in caller memory. Only password_hash()
 * output is persisted in the non-versioned runtime authentication store.
 */
final class LocalPasswordCredentialResetter implements
    LocalPasswordCredentialResetterInterface
{
    private const SITE_CONTRACT = 'OPUS_SITE_STANDARD_CONTRACT_CORE';
    private const SSO_CONTRACT = 'OPUS_GENERATED_APPLICATION_SSO_V1';
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
        string $password
    ): array {
        $siteId = trim($siteId);
        $subject = trim($subject);

        if (preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/', $siteId) !== 1) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_SITE_ID_INVALID'
            );
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._@-]{0,127}$/', $subject) !== 1) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_SUBJECT_INVALID'
            );
        }
        if (strlen($password) < 10) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_PASSWORD_TOO_SHORT'
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
            self::SITE_CONTRACT
        );
        if (($site['role'] ?? null) !== 'generated-opus-application'
            || ($site['generated_by'] ?? null) !== 'composer') {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_SITE_NOT_GENERATED'
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
                'OPUS_LOCAL_PASSWORD_RESET_PROVIDER_INACTIVE'
            );
        }

        $storeRelative = $this->runtimeStoreRelative((string) (
            $provider['runtime_store'] ?? ''
        ));
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
        if (($store['contract'] ?? null) !== self::STORE_CONTRACT) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_STORE_CONTRACT_INVALID'
            );
        }

        $users = is_array($store['users'] ?? null)
            ? $store['users']
            : [];
        $existing = $users[$subject] ?? null;
        if (!is_array($existing)) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_SUBJECT_UNKNOWN'
            );
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_HASH_FAILED'
            );
        }

        $now = gmdate('c');
        $existing['password_hash'] = $hash;
        $existing['must_change_password'] = false;
        $existing['password_changed_at'] = $now;
        $existing['updated_at'] = $now;
        $users[$subject] = $existing;

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
            'subject' => $subject,
            'runtime_store' => $storeRelative,
        ];
    }

    /** @return array<string,mixed> */
    private function config(string $path, string $contract): array
    {
        $data = $this->loader->read($path);
        if (($data['contract'] ?? null) !== $contract) {
            throw new \RuntimeException(
                'OPUS_LOCAL_PASSWORD_RESET_CONFIG_INVALID'
            );
        }

        return $data;
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
