<?php

declare(strict_types=1);

namespace Opus\Server;

use RuntimeException;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Load the declared OPUS multi-site registry for the current server.
 *
 * Responsibility:
 *   Convert the official runtime site registry file into typed site
 *   definitions for admin-only supervision surfaces.
 *
 * Contract:
 *   The registry file is mandatory for server overview dashboards. OPUS does
 *   not infer hidden sites from Apache or fall back to anonymous defaults.
 */
final class ServerSiteRegistry
{
    /** @param list<ServerSiteDefinition> $sites */
    private function __construct(private readonly array $sites)
    {
        if ($this->sites === []) {
            throw new RuntimeException('OPUS_SERVER_SITE_REGISTRY_EMPTY');
        }
    }

    public static function fromConfigFile(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException('OPUS_SERVER_SITE_REGISTRY_FILE_MISSING: ' . $path);
        }

        $data = require $path;
        if (!is_array($data)) {
            throw new RuntimeException('OPUS_SERVER_SITE_REGISTRY_DATA_INVALID');
        }

        $sites = [];
        foreach ($data as $index => $siteData) {
            if (!is_array($siteData)) {
                throw new RuntimeException('OPUS_SERVER_SITE_REGISTRY_ROW_INVALID: ' . (string) $index);
            }

            $sites[] = ServerSiteDefinition::fromArray($siteData);
        }

        return new self($sites);
    }

    /** @return list<ServerSiteDefinition> */
    public function sites(): array
    {
        return $this->sites;
    }

    public function count(): int
    {
        return count($this->sites);
    }
}