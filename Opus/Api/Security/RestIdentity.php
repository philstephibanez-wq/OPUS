<?php
declare(strict_types=1);

namespace Opus\Api\Security;

/** Authenticated REST_API service and user identity. */
final class RestIdentity implements RestIdentityInterface
{
    /** @param list<string> $roles */
    public function __construct(
        private readonly string $subjectValue,
        private readonly array $roleValues,
        private readonly string $provider,
        private readonly string $service
    ) {
        if ($this->subjectValue === '') {
            throw new \RuntimeException('OPUS_REST_API_IDENTITY_SUBJECT_INVALID');
        }
    }

    public function subject(): string
    {
        return $this->subjectValue;
    }

    public function isAnonymous(): bool
    {
        return false;
    }

    public function roles(): array
    {
        return $this->roleValues;
    }

    public function scopes(): array
    {
        return [];
    }

    public function claims(): array
    {
        return [
            'provider' => $this->provider,
            'service' => $this->service,
        ];
    }

    public function toArray(): array
    {
        return [
            'subject' => $this->subjectValue,
            'roles' => $this->roleValues,
            'provider' => $this->provider,
            'service' => $this->service,
        ];
    }
}
