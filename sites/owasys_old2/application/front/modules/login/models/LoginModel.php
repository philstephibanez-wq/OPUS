<?php
declare(strict_types=1);

final class OwasysLoginModel
{
    public function __construct(
        private readonly OwasysRuntimeUserStore $users,
        private readonly OwasysAuthSession $session
    ) {
    }

    /** @return array{ok:bool,error:?string,redirect:?string} */
    public function authenticate(string $username, string $password): array
    {
        if ($username === '' || $password === '') {
            return ['ok' => false, 'error' => 'auth.error.required_credentials', 'redirect' => null];
        }

        $candidate = $this->users->find($username);
        $hash = is_array($candidate) ? (string) ($candidate['password_hash'] ?? '') : '';

        if ($candidate === null || $hash === '' || !password_verify($password, $hash)) {
            return ['ok' => false, 'error' => 'auth.error.invalid_credentials', 'redirect' => null];
        }

        $mustChange = ($candidate['must_change_password'] ?? false) === true;

        $this->session->start([
            'id' => (string) ($candidate['id'] ?? $username),
            'label' => (string) ($candidate['label'] ?? $username),
            'profile' => (string) ($candidate['profile'] ?? 'dev'),
            'mode' => 'runtime-password-store',
            'must_change_password' => $mustChange,
            'started_at' => gmdate('c'),
        ]);

        return [
            'ok' => true,
            'error' => null,
            'redirect' => $mustChange ? '/account/password' : '/applications',
        ];
    }
}
