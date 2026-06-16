<?php

declare(strict_types=1);

namespace Opus\Server;

use InvalidArgumentException;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Describe one site supervised by the OPUS server control plane.
 *
 * Responsibility:
 *   Carry the immutable declared facts required to identify and supervise a
 *   site without reading Apache configuration directly from public routes.
 *
 * Contract:
 *   A site definition is explicit runtime configuration. Empty identifiers,
 *   hostnames, roots or security profiles are internal configuration errors.
 */
final class ServerSiteDefinition
{
    private function __construct(
        private readonly string $id,
        private readonly string $label,
        private readonly string $host,
        private readonly string $publicRoot,
        private readonly string $expectedFsmState,
        private readonly string $authProfile,
        private readonly string $aclProfile
    ) {
        foreach ([
            'id' => $this->id,
            'label' => $this->label,
            'host' => $this->host,
            'publicRoot' => $this->publicRoot,
            'expectedFsmState' => $this->expectedFsmState,
            'authProfile' => $this->authProfile,
            'aclProfile' => $this->aclProfile,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException('OPUS_SERVER_SITE_FIELD_EMPTY: ' . $field);
            }
        }
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            self::requiredString($data, 'id'),
            self::requiredString($data, 'label'),
            self::requiredString($data, 'host'),
            self::requiredString($data, 'public_root'),
            self::requiredString($data, 'expected_fsm_state'),
            self::requiredString($data, 'auth_profile'),
            self::requiredString($data, 'acl_profile')
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function publicRoot(): string
    {
        return $this->publicRoot;
    }

    public function expectedFsmState(): string
    {
        return $this->expectedFsmState;
    }

    public function authProfile(): string
    {
        return $this->authProfile;
    }

    public function aclProfile(): string
    {
        return $this->aclProfile;
    }

    private static function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('OPUS_SERVER_SITE_CONFIG_FIELD_INVALID: ' . $key);
        }

        return trim($value);
    }
}