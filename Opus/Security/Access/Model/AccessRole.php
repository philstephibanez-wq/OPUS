<?php
declare(strict_types=1);

namespace Opus\Security\Access\Model;

/** Immutable role definition associating permissions and inherited roles. */
final class AccessRole implements AccessRoleInterface
{
    /** @var list<string> */
    private array $permissions;
    /** @var list<string> */
    private array $parents;

    /** @param list<string> $permissions @param list<string> $parents */
    public function __construct(
        private readonly string $id,
        array $permissions,
        array $parents = []
    ) {
        self::assertIdentifier($id, 'ROLE');
        $this->permissions = self::identifiers($permissions, 'PERMISSION');
        $this->parents = self::identifiers($parents, 'PARENT', true);
        if (in_array($id, $this->parents, true)) {
            throw new \InvalidArgumentException('OPUS_ACL_ROLE_SELF_INHERITANCE:' . $id);
        }
    }

    public function id(): string { return $this->id; }

    public function permissions(): array { return $this->permissions; }

    public function parents(): array { return $this->parents; }

    public function toArray(): array
    {
        return ['id' => $this->id, 'permissions' => $this->permissions, 'parents' => $this->parents];
    }

    /** @param list<string> $values @return list<string> */
    private static function identifiers(array $values, string $field, bool $allowEmpty = false): array
    {
        $result = [];
        foreach ($values as $value) {
            self::assertIdentifier($value, $field);
            $result[$value] = true;
        }
        if ($result === [] && !$allowEmpty) {
            throw new \InvalidArgumentException('OPUS_ACL_' . $field . '_EMPTY');
        }
        return array_keys($result);
    }

    private static function assertIdentifier(string $value, string $field): void
    {
        if (preg_match('/^[a-z][a-z0-9.-]{0,127}$/D', $value) !== 1) {
            throw new \InvalidArgumentException('OPUS_ACL_' . $field . '_INVALID:' . $value);
        }
    }
}
