<?php
declare(strict_types=1);

namespace Opus\Security\Access\Model;

/** Immutable allow/deny rule over a role, permission and scope. */
final class AccessRule implements AccessRuleInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $effect,
        private readonly string $roleId,
        private readonly string $permissionId,
        private readonly AccessScopeInterface $scope
    ) {
        foreach (['RULE' => $id, 'ROLE' => $roleId, 'PERMISSION' => $permissionId] as $field => $value) {
            if (preg_match('/^[a-z][a-z0-9.-]{0,127}$/D', $value) !== 1) {
                throw new \InvalidArgumentException('OPUS_ACL_' . $field . '_INVALID:' . $value);
            }
        }
        if (!in_array($effect, ['allow', 'deny'], true)) {
            throw new \InvalidArgumentException('OPUS_ACL_RULE_EFFECT_INVALID:' . $effect);
        }
    }

    public function id(): string { return $this->id; }
    public function effect(): string { return $this->effect; }
    public function roleId(): string { return $this->roleId; }
    public function permissionId(): string { return $this->permissionId; }
    public function scope(): AccessScopeInterface { return $this->scope; }
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'effect' => $this->effect,
            'role_id' => $this->roleId,
            'permission_id' => $this->permissionId,
            'scope' => $this->scope->toArray(),
        ];
    }
}
