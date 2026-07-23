<?php
declare(strict_types=1);

namespace Opus\Rcp\Security;

/** Authenticated RCP service and user identity. */
final class RcpIdentity implements RcpIdentityInterface
{
    /** @param list<string> $roles */
    public function __construct(
        private readonly string $subjectValue,
        private readonly array $roleValues,
        private readonly string $provider,
        private readonly string $service
    ) {
        if ($this->subjectValue === '') {
            throw new \RuntimeException('OPUS_RCP_IDENTITY_SUBJECT_INVALID');
        }
    }

    public function subject(): string
    {
        return $this->subjectValue;
    }

    public function roles(): array
    {
        return $this->roleValues;
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
