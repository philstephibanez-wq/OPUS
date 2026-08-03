<?php
declare(strict_types=1);

namespace Opus\Security\Access\Model;

/** Explicit application, resource-type or resource assignment scope. */
final class AccessScope implements AccessScopeInterface
{
    public const APPLICATION = 'application';
    public const RESOURCE_TYPE = 'resource_type';
    public const RESOURCE = 'resource';

    public function __construct(
        private readonly string $level,
        private readonly string $applicationId,
        private readonly ?string $resourceType = null,
        private readonly ?string $resourceId = null
    ) {
        if (!in_array($level, [self::APPLICATION, self::RESOURCE_TYPE, self::RESOURCE], true)) {
            throw new \InvalidArgumentException('OPUS_ACL_SCOPE_LEVEL_INVALID:' . $level);
        }
        self::assertIdentifier($applicationId, 'APPLICATION');
        if ($level !== self::APPLICATION && $resourceType === null) {
            throw new \InvalidArgumentException('OPUS_ACL_SCOPE_RESOURCE_TYPE_MISSING');
        }
        if ($level === self::RESOURCE && $resourceId === null) {
            throw new \InvalidArgumentException('OPUS_ACL_SCOPE_RESOURCE_MISSING');
        }
        if ($level === self::APPLICATION && ($resourceType !== null || $resourceId !== null)) {
            throw new \InvalidArgumentException('OPUS_ACL_SCOPE_APPLICATION_OVERSPECIFIED');
        }
        if ($level === self::RESOURCE_TYPE && $resourceId !== null) {
            throw new \InvalidArgumentException('OPUS_ACL_SCOPE_RESOURCE_TYPE_OVERSPECIFIED');
        }
        if ($resourceType !== null) { self::assertIdentifier($resourceType, 'RESOURCE_TYPE'); }
        if ($resourceId !== null) { self::assertIdentifier($resourceId, 'RESOURCE'); }
    }

    public function level(): string { return $this->level; }
    public function applicationId(): string { return $this->applicationId; }
    public function resourceType(): ?string { return $this->resourceType; }
    public function resourceId(): ?string { return $this->resourceId; }

    public function contains(AccessResourceInterface $resource): bool
    {
        if ($resource->applicationId() !== $this->applicationId) { return false; }
        if ($this->level === self::APPLICATION) { return true; }
        if ($resource->type() !== $this->resourceType) { return false; }
        return $this->level === self::RESOURCE_TYPE || $resource->id() === $this->resourceId;
    }

    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'application_id' => $this->applicationId,
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
        ];
    }

    private static function assertIdentifier(string $value, string $field): void
    {
        if (preg_match('/^[a-z][a-z0-9.-]{0,127}$/D', $value) !== 1) {
            throw new \InvalidArgumentException('OPUS_ACL_' . $field . '_INVALID:' . $value);
        }
    }
}
