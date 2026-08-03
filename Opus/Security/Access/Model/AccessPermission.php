<?php
declare(strict_types=1);

namespace Opus\Security\Access\Model;

/** Immutable permission binding one known action to one resource type. */
final class AccessPermission implements AccessPermissionInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $resourceType,
        private readonly string $action
    ) {
        foreach (['PERMISSION' => $id, 'RESOURCE_TYPE' => $resourceType, 'ACTION' => $action] as $field => $value) {
            if (preg_match('/^[a-z][a-z0-9.-]{0,127}$/D', $value) !== 1) {
                throw new \InvalidArgumentException('OPUS_ACL_' . $field . '_INVALID:' . $value);
            }
        }
    }

    public function id(): string { return $this->id; }
    public function resourceType(): string { return $this->resourceType; }
    public function action(): string { return $this->action; }
    public function toArray(): array
    {
        return ['id' => $this->id, 'resource_type' => $this->resourceType, 'action' => $this->action];
    }
}
