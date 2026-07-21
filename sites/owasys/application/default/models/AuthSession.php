<?php
declare(strict_types=1);

final class OwasysAuthSession
{
    public function user(): ?array
    {
        $user = $_SESSION['owasys_user'] ?? null;

        return is_array($user) ? $user : null;
    }

    public function isAuthenticated(): bool
    {
        return $this->user() !== null;
    }

    /** @param array<string,mixed> $user */
    public function start(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['owasys_user'] = $user;
    }

    public function clear(): void
    {
        unset($_SESSION['owasys_user'], $_SESSION['owasys_current_app']);
        session_regenerate_id(true);
    }
}
