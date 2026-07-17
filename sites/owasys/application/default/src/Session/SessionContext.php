<?php
declare(strict_types=1);

namespace Owasys\Application\Session;

use RuntimeException;

final class SessionContext
{
    private string $name;

    public function __construct(string $name)
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $name) !== 1) {
            throw new RuntimeException('OWASYS_SESSION_NAME_INVALID');
        }
        $this->name = $name;
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name($this->name);
            if (!session_start()) {
                throw new RuntimeException('OWASYS_SESSION_START_FAILED');
            }
        }
    }

    public function locale(string $defaultLocale): string
    {
        return (string) ($_SESSION['owasys_locale'] ?? $defaultLocale);
    }

    public function setLocale(string $locale): void
    {
        $_SESSION['owasys_locale'] = $locale;
    }

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        return is_array($_SESSION['owasys_user'] ?? null) ? $_SESSION['owasys_user'] : null;
    }

    /** @return array<string,mixed>|null */
    public function currentApplication(): ?array
    {
        return is_array($_SESSION['owasys_current_app'] ?? null) ? $_SESSION['owasys_current_app'] : null;
    }

    public function currentState(string $fallback = 'home'): string
    {
        $state = (string) ($_SESSION['owasys_current_state'] ?? $fallback);
        return $state === '' ? $fallback : $state;
    }

    public function csrfToken(): string
    {
        $token = (string) ($_SESSION['owasys_csrf'] ?? '');
        if ($token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION['owasys_csrf'] = $token;
        }
        return $token;
    }

    public function assertCsrfToken(string $provided): void
    {
        $expected = (string) ($_SESSION['owasys_csrf'] ?? '');
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            throw new RuntimeException('OWASYS_CSRF_INVALID');
        }
    }
}
