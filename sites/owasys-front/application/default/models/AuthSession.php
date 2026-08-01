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
            $normalized = $this->normalizeIdentity($identity);
            $_SESSION[self::IDENTITY_KEY] = $normalized;

            return $normalized;
        }

        $legacy = $_SESSION[self::LEGACY_IDENTITY_KEY] ?? null;
        if (!is_array($legacy)) {
            return null;
        }

        $normalized = $this->normalizeIdentity($legacy);
        $_SESSION[self::IDENTITY_KEY] = $normalized;
        unset($_SESSION[self::LEGACY_IDENTITY_KEY]);

        return $normalized;
    }

    public function isAuthenticated(): bool
    {
        return $this->user() !== null;
    }

    /** @param array<string,mixed> $identity */
    public function start(array $identity): void
    {
        $normalized = $this->normalizeIdentity($identity);
        session_regenerate_id(true);
        $_SESSION[self::IDENTITY_KEY] = $normalized;
        unset($_SESSION[self::LEGACY_IDENTITY_KEY]);
    }

    /** @param array<string,mixed> $identity */
    public function update(array $identity): void
    {
        if (!$this->isAuthenticated()) {
            throw new RuntimeException('OWASYS_SSO_SESSION_NOT_AUTHENTICATED');
        }

        $_SESSION[self::IDENTITY_KEY] = $this->normalizeIdentity($identity);
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

    /**
     * Normalizes the single session identity contract consumed by UI, FSM and ACL.
     *
     * The historical profile field is accepted only when the roles field is absent.
     * An explicitly empty or malformed roles field remains invalid and cannot be
     * promoted through profile.
     *
     * @param array<string,mixed> $identity
     * @return array<string,mixed>
     */
    private function normalizeIdentity(array $identity): array
    {
        $subject = trim((string) ($identity['subject'] ?? $identity['id'] ?? ''));
        if ($subject === '') {
            throw new RuntimeException('OWASYS_SSO_SESSION_IDENTITY_INVALID');
        }

        if (array_key_exists('roles', $identity)) {
            if (!is_array($identity['roles'])) {
                throw new RuntimeException('OWASYS_SSO_SESSION_ROLES_INVALID');
            }
            $roles = $this->normalizeRoles($identity['roles']);
        } else {
            $profile = trim((string) ($identity['profile'] ?? ''));
            $roles = $profile === '' ? [] : [$profile];
        }

        if ($roles === []) {
            throw new RuntimeException('OWASYS_SSO_SESSION_ROLES_INVALID');
        }

        $label = trim((string) ($identity['label'] ?? $subject));
        $provider = trim((string) ($identity['provider'] ?? 'local-password'));
        if ($provider === '') {
            throw new RuntimeException('OWASYS_SSO_SESSION_PROVIDER_INVALID');
        }

        return [
            'subject' => $subject,
            'id' => $subject,
            'label' => $label === '' ? $subject : $label,
            'roles' => $roles,
            'profile' => $roles[0],
            'provider' => $provider,
            'must_change_password' => ($identity['must_change_password'] ?? false) === true,
            'authenticated_at' => trim((string) (
                $identity['authenticated_at']
                    ?? $identity['started_at']
                    ?? gmdate('c')
            )),
        ];
    }

    /** @param array<mixed> $roles @return list<string> */
    private function normalizeRoles(array $roles): array
    {
        $normalized = [];
        foreach ($roles as $role) {
            if (!is_string($role)) {
                throw new RuntimeException('OWASYS_SSO_SESSION_ROLES_INVALID');
            }
            $role = trim($role);
            if ($role === '') {
                throw new RuntimeException('OWASYS_SSO_SESSION_ROLES_INVALID');
            }
            $normalized[$role] = true;
        }

        return array_keys($normalized);
    }
}
