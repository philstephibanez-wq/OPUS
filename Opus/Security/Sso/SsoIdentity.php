<?php
declare(strict_types=1);

namespace Opus\Security\Sso;

final class SsoIdentity
{
    /** @param list<string> $roles */
    public function __construct(
        public readonly string $subject,
        public readonly string $label,
        public readonly array $roles,
        public readonly string $provider,
        public readonly bool $mustChangePassword = false
    ) {
    }

    /** @return array<string,mixed> */
    public function toSession(): array
    {
        return [
            'subject' => $this->subject,
            'id' => $this->subject,
            'label' => $this->label,
            'roles' => $this->roles,
            'profile' => $this->roles[0] ?? 'viewer',
            'provider' => $this->provider,
            'must_change_password' => $this->mustChangePassword,
            'authenticated_at' => gmdate('c'),
        ];
    }
}
