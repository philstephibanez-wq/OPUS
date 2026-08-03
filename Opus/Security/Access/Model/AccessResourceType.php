<?php
declare(strict_types=1);

namespace Opus\Security\Access\Model;

/** Immutable resource-type definition with an explicit action allow-list. */
final class AccessResourceType implements AccessResourceTypeInterface
{
    /** @var list<string> */
    private array $actions;

    /** @param list<string> $actions */
    public function __construct(private readonly string $id, array $actions)
    {
        self::assertIdentifier($id, 'RESOURCE_TYPE');
        $normalized = [];
        foreach ($actions as $action) {
            self::assertIdentifier($action, 'ACTION');
            $normalized[$action] = true;
        }
        if ($normalized === []) {
            throw new \InvalidArgumentException('OPUS_ACL_RESOURCE_TYPE_ACTIONS_EMPTY:' . $id);
        }
        $this->actions = array_keys($normalized);
    }

    public function id(): string { return $this->id; }
    public function actions(): array { return $this->actions; }
    public function toArray(): array { return ['id' => $this->id, 'actions' => $this->actions]; }

    private static function assertIdentifier(string $value, string $field): void
    {
        if (preg_match('/^[a-z][a-z0-9.-]{0,127}$/D', $value) !== 1) {
            throw new \InvalidArgumentException('OPUS_ACL_' . $field . '_INVALID:' . $value);
        }
    }
}
