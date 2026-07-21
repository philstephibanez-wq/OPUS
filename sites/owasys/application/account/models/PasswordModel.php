<?php
declare(strict_types=1);

final class OwasysPasswordModel
{
    public function __construct(
        private readonly OwasysRuntimeUserStore $users,
        private readonly OwasysAuthSession $session,
        private readonly int $minimumLength = 10
    ) {
    }

    /** @return array{ok:bool,error:?string,redirect:?string} */
    public function change(string $currentPassword, string $newPassword, string $confirmation): array
    {
        $sessionUser = $this->session->user();
        $userId = is_array($sessionUser) ? (string) ($sessionUser['id'] ?? '') : '';

        if ($userId === '') {
            return ['ok' => false, 'error' => 'auth.error.runtime_user_missing', 'redirect' => null];
        }

        $candidate = $this->users->find($userId);
        $hash = is_array($candidate) ? (string) ($candidate['password_hash'] ?? '') : '';

        if ($candidate === null || $hash === '') {
            return ['ok' => false, 'error' => 'auth.error.runtime_user_missing', 'redirect' => null];
        }

        if ($currentPassword === '' || !password_verify($currentPassword, $hash)) {
            return ['ok' => false, 'error' => 'auth.error.current_password_invalid', 'redirect' => null];
        }

        if (strlen($newPassword) < $this->minimumLength) {
            return ['ok' => false, 'error' => 'auth.error.new_password_too_short', 'redirect' => null];
        }

        if ($newPassword !== $confirmation) {
            return ['ok' => false, 'error' => 'auth.error.confirmation_mismatch', 'redirect' => null];
        }

        if (password_verify($newPassword, $hash)) {
            return ['ok' => false, 'error' => 'auth.error.password_unchanged', 'redirect' => null];
        }

        $candidate['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $candidate['must_change_password'] = false;
        $candidate['password_changed_at'] = gmdate('c');
        $candidate['updated_at'] = gmdate('c');
        $this->users->saveUser($userId, $candidate);

        $_SESSION['owasys_user']['must_change_password'] = false;
        $_SESSION['owasys_user']['password_changed_at'] = $candidate['password_changed_at'];

        return ['ok' => true, 'error' => null, 'redirect' => '/applications'];
    }
}
