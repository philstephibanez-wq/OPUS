<?php
declare(strict_types=1);

namespace Opus\Security\Sso;

use Opus\File\File;
use Opus\File\FileInterface;
use Opus\File\Json;
use Opus\File\JsonInterface;
use RuntimeException;

final class LocalPasswordSsoProvider implements SsoProviderInterface, PasswordChangeProviderInterface, LocalPasswordSsoProviderInterface
{
    private readonly FileInterface $file;
    private readonly JsonInterface $json;

    public function __construct(
        private readonly string $storeFile,
        private readonly int $minimumPasswordLength = 10,
        private readonly string $storeContract = 'OPUS_LOCAL_USER_STORE_V1',
        ?FileInterface $file = null,
        ?JsonInterface $json = null
    ) {
        if ($this->minimumPasswordLength < 8) {
            throw new RuntimeException('OPUS_SSO_MINIMUM_PASSWORD_LENGTH_INVALID');
        }
        if (preg_match('/^[A-Z][A-Z0-9_]{2,127}_V[0-9]+$/', $this->storeContract) !== 1) {
            throw new RuntimeException('OPUS_SSO_STORE_CONTRACT_INVALID');
        }
        $this->file = $file ?? File::instance();
        $this->json = $json ?? Json::instance();
    }

    public function id(): string
    {
        return 'local-password';
    }

    public function authenticate(array $credentials): ?SsoIdentity
    {
        $username = trim((string) ($credentials['username'] ?? ''));
        $password = (string) ($credentials['password'] ?? '');

        if ($username === '' || $password === '') {
            return null;
        }

        $store = $this->readStore();
        $candidate = $store['users'][$username] ?? null;

        if (!is_array($candidate)) {
            return null;
        }

        $hash = (string) ($candidate['password_hash'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            return null;
        }

        return $this->identityFromUser($username, $candidate);
    }

    public function changePassword(
        string $subject,
        string $currentPassword,
        string $newPassword
    ): SsoIdentity {
        if ($subject === '') {
            throw new RuntimeException('OPUS_SSO_SUBJECT_REQUIRED');
        }

        if (strlen($newPassword) < $this->minimumPasswordLength) {
            throw new RuntimeException('OPUS_SSO_NEW_PASSWORD_TOO_SHORT');
        }

        $store = $this->readStore();
        [$username, $candidate] = $this->findUserBySubject($store, $subject);
        $hash = (string) ($candidate['password_hash'] ?? '');

        if ($hash === '' || !password_verify($currentPassword, $hash)) {
            throw new RuntimeException('OPUS_SSO_CURRENT_PASSWORD_INVALID');
        }

        if (password_verify($newPassword, $hash)) {
            throw new RuntimeException('OPUS_SSO_PASSWORD_UNCHANGED');
        }

        $candidate['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $candidate['must_change_password'] = false;
        $candidate['password_changed_at'] = gmdate('c');
        $candidate['updated_at'] = gmdate('c');
        $store['users'][$username] = $candidate;
        $store['updated_at'] = gmdate('c');

        $this->writeStore($store);

        return $this->identityFromUser($username, $candidate);
    }

    /** @return array<string,mixed> */
    private function readStore(): array
    {
        if (!$this->file->exists($this->storeFile)) {
            throw new RuntimeException('OPUS_SSO_LOCAL_STORE_MISSING:' . $this->storeFile);
        }

        $decoded = $this->json->parse(
            $this->file->read($this->storeFile),
            $this->storeFile
        );
        if (($decoded['contract'] ?? null) !== $this->storeContract) {
            throw new RuntimeException('OPUS_SSO_LOCAL_STORE_INVALID:' . $this->storeFile);
        }

        $decoded['users'] = is_array($decoded['users'] ?? null) ? $decoded['users'] : [];

        return $decoded;
    }

    /** @param array<string,mixed> $store */
    private function writeStore(array $store): void
    {
        $this->file->writeAtomic(
            $this->storeFile,
            $this->json->encode($store, true)
        );
    }

    /**
     * @param array<string,mixed> $store
     * @return array{0:string,1:array<string,mixed>}
     */
    private function findUserBySubject(array $store, string $subject): array
    {
        foreach ((array) ($store['users'] ?? []) as $username => $candidate) {
            if (!is_string($username) || !is_array($candidate)) {
                continue;
            }

            if ($username === $subject || (string) ($candidate['id'] ?? '') === $subject) {
                return [$username, $candidate];
            }
        }

        throw new RuntimeException('OPUS_SSO_SUBJECT_UNKNOWN');
    }

    /** @param array<string,mixed> $candidate */
    private function identityFromUser(string $username, array $candidate): SsoIdentity
    {
        $roles = is_array($candidate['roles'] ?? null)
            ? array_values(array_filter($candidate['roles'], 'is_string'))
            : [(string) ($candidate['profile'] ?? 'viewer')];

        if ($roles === []) {
            $roles = ['viewer'];
        }

        return new SsoIdentity(
            (string) ($candidate['id'] ?? $username),
            (string) ($candidate['label'] ?? $username),
            $roles,
            $this->id(),
            ($candidate['must_change_password'] ?? false) === true
        );
    }
}
