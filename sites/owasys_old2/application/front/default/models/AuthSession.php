<?php
declare(strict_types=1);

final class OwasysAuthSession
{
    private const IDENTITY_KEY = 'owasys_sso_identity';
    private const LEGACY_IDENTITY_KEY = 'owasys_user';
    private const CURRENT_APP_KEY = 'owasys_current_app';

    /** @return array<string,mixed>|null */
    public function user(): ?array
    {
        $identity = $_SESSION[self::IDENTITY_KEY] ?? null;
        if (is_array($identity)) {
            return $identity;
        }

        $legacy = $_SESSION[self::LEGACY_IDENTITY_KEY] ?? null;
        if (!is_array($legacy)) {
            return null;
        }

        $roles = is_array($legacy['roles'] ?? null)
            ? array_values(array_filter($legacy['roles'], 'is_string'))
            : [(string) ($legacy['profile'] ?? 'viewer')];

        $identity = [
            'subject' => (string) ($legacy['subject'] ?? $legacy['id'] ?? ''),
            'id' => (string) ($legacy['id'] ?? $legacy['subject'] ?? ''),
            'label' => (string) ($legacy['label'] ?? $legacy['id'] ?? ''),
            'roles' => $roles === [] ? ['viewer'] : $roles,
            'profile' => (string) ($legacy['profile'] ?? ($roles[0] ?? 'viewer')),
            'provider' => (string) ($legacy['provider'] ?? 'local-password'),
            'must_change_password' => ($legacy['must_change_password'] ?? false) === true,
            'authenticated_at' => (string) ($legacy['authenticated_at'] ?? $legacy['started_at'] ?? gmdate('c')),
        ];

        $_SESSION[self::IDENTITY_KEY] = $identity;
        unset($_SESSION[self::LEGACY_IDENTITY_KEY]);

        return $identity;
    }

    public function isAuthenticated(): bool
    {
        $identity = $this->user();

        return is_array($identity) && (string) ($identity['subject'] ?? $identity['id'] ?? '') !== '';
    }

    /** @param array<string,mixed> $identity */
    public function start(array $identity): void
    {
        if ((string) ($identity['subject'] ?? $identity['id'] ?? '') === '') {
            throw new RuntimeException('OWASYS_SSO_SESSION_IDENTITY_INVALID');
        }

        session_regenerate_id(true);
        $_SESSION[self::IDENTITY_KEY] = $identity;
        unset($_SESSION[self::LEGACY_IDENTITY_KEY]);
    }

    /** @param array<string,mixed> $identity */
    public function update(array $identity): void
    {
        if (!$this->isAuthenticated()) {
            throw new RuntimeException('OWASYS_SSO_SESSION_NOT_AUTHENTICATED');
        }

        $_SESSION[self::IDENTITY_KEY] = $identity;
    }

    /** @return array<string,mixed>|null */
    public function currentApp(): ?array
    {
        $current = $_SESSION[self::CURRENT_APP_KEY] ?? null;

        return is_array($current) ? $current : null;
    }

    /** @param array<string,mixed> $application */
    public function setCurrentApp(array $application): void
    {
        $_SESSION[self::CURRENT_APP_KEY] = $application;
    }

    public function clearCurrentApp(): void
    {
        unset($_SESSION[self::CURRENT_APP_KEY]);
    }

    public function clearIdentity(): void
    {
        unset($_SESSION[self::IDENTITY_KEY], $_SESSION[self::LEGACY_IDENTITY_KEY]);
        session_regenerate_id(true);
    }

    public function clear(): void
    {
        unset(
            $_SESSION[self::IDENTITY_KEY],
            $_SESSION[self::LEGACY_IDENTITY_KEY],
            $_SESSION[self::CURRENT_APP_KEY]
        );
        session_regenerate_id(true);
    }
}
