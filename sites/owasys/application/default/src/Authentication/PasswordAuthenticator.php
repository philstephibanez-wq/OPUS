<?php
declare(strict_types=1);

namespace Owasys\Application\Authentication;

final class PasswordAuthenticator
{
    private LocalUserStore $store;

    public function __construct(LocalUserStore $store)
    {
        $this->store = $store;
    }

    /** @return array<string,mixed>|null */
    public function authenticate(string $username, string $password): ?array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return null;
        }

        $candidate = $this->store->find($username);
        if ($candidate === null) {
            return null;
        }

        $hash = (string) ($candidate['password_hash'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            return null;
        }

        return [
            'id' => (string) ($candidate['id'] ?? $username),
            'label' => (string) ($candidate['label'] ?? $username),
            'profile' => (string) ($candidate['profile'] ?? 'dev'),
            'mode' => 'runtime-password-store',
            'must_change_password' => ($candidate['must_change_password'] ?? false) === true,
        ];
    }
}
