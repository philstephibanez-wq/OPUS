<?php

declare(strict_types=1);

namespace Opus\Server;

use InvalidArgumentException;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Carry the read-only server overview prepared for the admin dashboard.
 *
 * Contract:
 *   This snapshot is admin-only. It may contain internal paths and state names
 *   and must never be rendered through public blocked responses.
 */
final class ServerOverviewSnapshot
{
    /** @param list<array<string,string|bool>> $sites */
    public function __construct(
        private readonly string $generatedAt,
        private readonly string $currentHost,
        private readonly string $serverState,
        private readonly int $siteCount,
        private readonly int $blockedSiteCount,
        private readonly array $sites
    ) {
        if ($this->generatedAt === '' || $this->currentHost === '' || $this->serverState === '') {
            throw new InvalidArgumentException('OPUS_SERVER_OVERVIEW_SNAPSHOT_FIELD_EMPTY');
        }
        if ($this->siteCount < 1 || $this->blockedSiteCount < 0) {
            throw new InvalidArgumentException('OPUS_SERVER_OVERVIEW_SNAPSHOT_COUNT_INVALID');
        }
        foreach ($this->sites as $site) {
            foreach (['id','label','host','site_type','engine_root','site_root','public_root','engine_root_state','site_root_state','public_root_state','root_audit','fsm_state','health','auth_profile','acl_profile','routes_profile','api_profile','enabled'] as $field) {
                if (!array_key_exists($field, $site)) {
                    throw new InvalidArgumentException('OPUS_SERVER_OVERVIEW_SITE_SNAPSHOT_FIELD_MISSING: ' . $field);
                }
            }
        }
    }

    public function generatedAt(): string { return $this->generatedAt; }
    public function currentHost(): string { return $this->currentHost; }
    public function serverState(): string { return $this->serverState; }
    public function siteCount(): int { return $this->siteCount; }
    public function blockedSiteCount(): int { return $this->blockedSiteCount; }
    /** @return list<array<string,string|bool>> */
    public function sites(): array { return $this->sites; }
}
