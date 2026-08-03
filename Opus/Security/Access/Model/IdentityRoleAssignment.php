<?php
declare(strict_types=1);

namespace Opus\Security\Access\Model;

/** SSO identity-to-role assignment constrained by an explicit scope. */
final class IdentityRoleAssignment implements IdentityRoleAssignmentInterface
{
    public function __construct(
        private readonly string $provider,
        private readonly string $subject,
        private readonly string $roleId,
        private readonly AccessScopeInterface $scope
    ) {
        if (preg_match('/^[a-z][a-z0-9.-]{0,127}$/D', $provider) !== 1) {
            throw new \InvalidArgumentException('OPUS_ACL_ASSIGNMENT_PROVIDER_INVALID:' . $provider);
        }
        if ($subject === '' || strlen($subject) > 240 || preg_match('/[\x00-\x1F\x7F]/', $subject) === 1) {
            throw new \InvalidArgumentException('OPUS_ACL_ASSIGNMENT_SUBJECT_INVALID');
        }
        if (preg_match('/^[a-z][a-z0-9.-]{0,127}$/D', $roleId) !== 1) {
            throw new \InvalidArgumentException('OPUS_ACL_ASSIGNMENT_ROLE_INVALID:' . $roleId);
        }
    }

    public function provider(): string { return $this->provider; }
    public function subject(): string { return $this->subject; }
    public function roleId(): string { return $this->roleId; }
    public function scope(): AccessScopeInterface { return $this->scope; }

    public function appliesTo(string $provider, string $subject, AccessResourceInterface $resource): bool
    {
        return hash_equals($this->provider, $provider)
            && hash_equals($this->subject, $subject)
            && $this->scope->contains($resource);
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'subject' => $this->subject,
            'role_id' => $this->roleId,
            'scope' => $this->scope->toArray(),
        ];
    }
}
