<?php
declare(strict_types=1);

namespace Opus\Security\Sso;

use RuntimeException;

final class LocalPasswordSsoProvider implements SsoProviderInterface, PasswordChangeProviderInterface
{
    public function __construct(
        private readonly string $storeFile,
        private readonly int $minimumPasswordLength = 10
    ) {
        if ($this->minimumPasswordLength < 8) {
            throw new RuntimeException('OPUS_SSO_MINIMUM_PASSWORD_LENGTH_INVALID');
        }
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
        if (!is_file($this->storeFile)) {
            throw new RuntimeException('OWASYS_SSO_LOCAL_STORE_MISSING:' . $this->storeFile);
        }

        $decoded = json_decode((string) file_get_contents($this->storeFile), true);
        if (!is_array($decoded) || ($decoded['contract'] ?? null) !== 'OWASYS_LOCAL_USER_STORE_V1') {
            throw new RuntimeException('OWASYS_SSO_LOCAL_STORE_INVALID:' . $this->storeFile);
        }

        $decoded['users'] = is_array($decoded['users'] ?? null) ? $decoded['users'] : [];

        return $decoded;
    }

    /** @param array<string,mixed> $store */
    private function writeStore(array $store): void
    {
        $directory = dirname($this->storeFile);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('OWASYS_SSO_LOCAL_STORE_DIRECTORY_FAILED');
        }

        $encoded = json_encode(
            $store,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        if (file_put_contents($this->storeFile, $encoded . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('OWASYS_SSO_LOCAL_STORE_WRITE_FAILED');
        }
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
