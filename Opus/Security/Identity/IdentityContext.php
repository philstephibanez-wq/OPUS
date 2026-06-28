<?php
declare(strict_types=1);

namespace Opus\Security\Identity;

/**
 * Immutable OPUS identity context resolved by an SSO authenticator.
 */
final class IdentityContext implements IdentityContextInterface
{
    private string $subject;
    /** @var list<string> */
    private array $roles;
    /** @var list<string> */
    private array $scopes;
    /** @var array<string,mixed> */
    private array $claims;
    private bool $anonymous;

    /**
     * @param list<string> $roles
     * @param list<string> $scopes
     * @param array<string,mixed> $claims
     */
    public function __construct(string $subject, array $roles = [], array $scopes = [], array $claims = [], bool $anonymous = false)
    {
        $this->subject = $subject;
        $this->roles = array_values(array_unique(array_filter(array_map('strval', $roles), static fn (string $value): bool => $value !== '')));
        $this->scopes = array_values(array_unique(array_filter(array_map('strval', $scopes), static fn (string $value): bool => $value !== '')));
        $this->claims = $claims;
        $this->anonymous = $anonymous;
    }

    public static function anonymous(string $subject = 'anonymous'): self
    {
        return new self($subject, [], [], ['auth_method' => 'anonymous'], true);
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function isAnonymous(): bool
    {
        return $this->anonymous;
    }

    public function roles(): array
    {
        return $this->roles;
    }

    public function scopes(): array
    {
        return $this->scopes;
    }

    public function claims(): array
    {
        return $this->claims;
    }

    public function toArray(): array
    {
        return [
            'subject' => $this->subject,
            'anonymous' => $this->anonymous,
            'roles' => $this->roles,
            'scopes' => $this->scopes,
            'claims' => $this->claims,
        ];
    }
}
