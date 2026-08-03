<?php
declare(strict_types=1);

namespace Opus\Security\Access\Model;

/** Canonical resource instance owned by one OPUS application. */
final class AccessResource implements AccessResourceInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $type,
        private readonly string $applicationId
    ) {
        foreach (['RESOURCE' => $id, 'RESOURCE_TYPE' => $type, 'APPLICATION' => $applicationId] as $field => $value) {
            if (preg_match('/^[a-z][a-z0-9.-]{0,127}$/D', $value) !== 1) {
                throw new \InvalidArgumentException('OPUS_ACL_' . $field . '_INVALID:' . $value);
            }
        }
    }

    public function id(): string { return $this->id; }
    public function type(): string { return $this->type; }
    public function applicationId(): string { return $this->applicationId; }
    public function toArray(): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'application_id' => $this->applicationId];
    }
}
