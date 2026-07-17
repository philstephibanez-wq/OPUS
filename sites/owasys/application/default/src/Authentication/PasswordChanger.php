<?php
declare(strict_types=1);

namespace Owasys\Application\Authentication;

use RuntimeException;

final class PasswordChanger
{
    private LocalUserStore $store;

    public function __construct(LocalUserStore $store)
    {
        $this->store = $store;
    }

    /** @return array<string,mixed> */
    public function change(string $userId, string $currentPassword, string $newPassword, string $confirmation): array
    {
        $userId = trim($userId);
        if ($userId === '') {
            throw new RuntimeException('OWASYS_PASSWORD_CHANGE_USER_REQUIRED');
        }

        $store = $this->store->read();
        $users = is_array($store['users'] ?? null) ? $store['users'] : [];
        $candidate = is_array($users[$userId] ?? null) ? $users[$userId] : null;
        if ($candidate === null) {
            throw new RuntimeException('OWASYS_PASSWORD_CHANGE_USER_MISSING');
        }

        $currentHash = (string) ($candidate['password_hash'] ?? '');
        if ($currentPassword === '' || $currentHash === '' || !password_verify($currentPassword, $currentHash)) {
            throw new RuntimeException('OWASYS_PASSWORD_CHANGE_CURRENT_INVALID');
        }
        if (strlen($newPassword) < 10) {
            throw new RuntimeException('OWASYS_PASSWORD_CHANGE_TOO_SHORT');
        }
        if ($newPassword !== $confirmation) {
            throw new RuntimeException('OWASYS_PASSWORD_CHANGE_CONFIRMATION_MISMATCH');
        }
        if (password_verify($newPassword, $currentHash)) {
            throw new RuntimeException('OWASYS_PASSWORD_CHANGE_UNCHANGED');
        }

        $changedAt = gmdate('c');
        $candidate['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $candidate['must_change_password'] = false;
        $candidate['password_changed_at'] = $changedAt;
        $candidate['updated_at'] = $changedAt;
        $users[$userId] = $candidate;
        $store['users'] = $users;
        $this->store->write($store);

        return [
            'id' => (string) ($candidate['id'] ?? $userId),
            'label' => (string) ($candidate['label'] ?? $userId),
            'profile' => (string) ($candidate['profile'] ?? 'dev'),
            'mode' => 'runtime-password-store',
            'must_change_password' => false,
            'password_changed_at' => $changedAt,
        ];
    }
}
