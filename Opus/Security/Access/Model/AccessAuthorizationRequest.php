<?php
declare(strict_types=1);

namespace Opus\Security\Access\Model;

/** Typed authorization question for one SSO identity, action and resource. */
final class AccessAuthorizationRequest implements AccessAuthorizationRequestInterface
{
    public function __construct(
        private readonly string $provider,
        private readonly string $subject,
        private readonly AccessPermissionInterface $permission,
        private readonly AccessResourceInterface $resource
    ) {
        if (preg_match('/^[a-z][a-z0-9.-]{0,127}$/D', $provider) !== 1) {
            throw new \InvalidArgumentException('OPUS_ACL_REQUEST_PROVIDER_INVALID:' . $provider);
        }
        if ($subject === '' || strlen($subject) > 240 || preg_match('/[\x00-\x1F\x7F]/', $subject) === 1) {
            throw new \InvalidArgumentException('OPUS_ACL_REQUEST_SUBJECT_INVALID');
        }
        if ($permission->resourceType() !== $resource->type()) {
            throw new \InvalidArgumentException(
                'OPUS_ACL_REQUEST_RESOURCE_TYPE_MISMATCH:'
                . $permission->resourceType()
                . ':'
                . $resource->type()
            );
        }
    }

    public function provider(): string { return $this->provider; }
    public function subject(): string { return $this->subject; }
    public function permission(): AccessPermissionInterface { return $this->permission; }
    public function resource(): AccessResourceInterface { return $this->resource; }
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'subject' => $this->subject,
            'permission' => $this->permission->toArray(),
            'resource' => $this->resource->toArray(),
        ];
    }
}
