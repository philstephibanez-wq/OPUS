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
 *   Carry immutable declared facts required to identify and supervise a site
 *   without reading Apache configuration directly from public routes.
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
        private readonly string $siteType,
        private readonly string $engineRoot,
        private readonly string $siteRoot,
        private readonly string $publicRoot,
        private readonly string $expectedFsmState,
        private readonly string $authProfile,
        private readonly string $aclProfile,
        private readonly string $routesProfile,
        private readonly string $apiProfile,
        private readonly bool $enabled
    ) {
        foreach ([
            'id' => $this->id,
            'label' => $this->label,
            'host' => $this->host,
            'siteType' => $this->siteType,
            'engineRoot' => $this->engineRoot,
            'siteRoot' => $this->siteRoot,
            'publicRoot' => $this->publicRoot,
            'expectedFsmState' => $this->expectedFsmState,
            'authProfile' => $this->authProfile,
            'aclProfile' => $this->aclProfile,
            'routesProfile' => $this->routesProfile,
            'apiProfile' => $this->apiProfile,
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
            self::requiredString($data, 'site_type'),
            self::requiredString($data, 'engine_root'),
            self::requiredString($data, 'site_root'),
            self::requiredString($data, 'public_root'),
            self::requiredString($data, 'expected_fsm_state'),
            self::requiredString($data, 'auth_profile'),
            self::requiredString($data, 'acl_profile'),
            self::requiredString($data, 'routes_profile'),
            self::requiredString($data, 'api_profile'),
            self::requiredBool($data, 'enabled')
        );
    }

    public function id(): string { return $this->id; }
    public function label(): string { return $this->label; }
    public function host(): string { return $this->host; }
    public function siteType(): string { return $this->siteType; }
    public function engineRoot(): string { return $this->engineRoot; }
    public function siteRoot(): string { return $this->siteRoot; }
    public function publicRoot(): string { return $this->publicRoot; }
    public function expectedFsmState(): string { return $this->expectedFsmState; }
    public function authProfile(): string { return $this->authProfile; }
    public function aclProfile(): string { return $this->aclProfile; }
    public function routesProfile(): string { return $this->routesProfile; }
    public function apiProfile(): string { return $this->apiProfile; }
    public function enabled(): bool { return $this->enabled; }

    private static function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('OPUS_SERVER_SITE_CONFIG_FIELD_INVALID: ' . $key);
        }
        return trim($value);
    }

    private static function requiredBool(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;
        if (!is_bool($value)) {
            throw new InvalidArgumentException('OPUS_SERVER_SITE_CONFIG_FIELD_INVALID: ' . $key);
        }
        return $value;
    }
}
