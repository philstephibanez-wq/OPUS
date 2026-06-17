<?php

declare(strict_types=1);

namespace Opus\Server;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Build the read-only OPUS server supervision snapshot.
 *
 * Contract:
 *   This service does not mutate server state, Apache configuration, sessions,
 *   ACL, tokens or FSM states. It prepares observations only.
 */
final class ServerSiteSupervisor
{
    public function __construct(private readonly ServerSiteRegistry $registry) {}

    public function snapshot(string $currentHost): ServerOverviewSnapshot
    {
        $normalizedCurrentHost = $this->normalizeHost($currentHost);
        $sites = [];
        $blocked = 0;

        foreach ($this->registry->sites() as $definition) {
            $engineRootExists = is_dir($definition->engineRoot());
            $siteRootExists = is_dir($definition->siteRoot());
            $publicRootExists = is_dir($definition->publicRoot());
            $enabled = $definition->enabled();

            if (!$enabled) {
                $fsmState = 'SITE_DISABLED';
                $health = 'DISABLED';
            } elseif ($engineRootExists && $siteRootExists && $publicRootExists) {
                $fsmState = $definition->expectedFsmState();
                $health = 'OK';
            } else {
                $fsmState = 'SITE_BLOCKED';
                $health = 'BLOCKED';
                ++$blocked;
            }

            $sites[] = [
                'id' => $definition->id(),
                'label' => $definition->label(),
                'host' => $definition->host(),
                'site_type' => $definition->siteType(),
                'engine_root' => $definition->engineRoot(),
                'site_root' => $definition->siteRoot(),
                'public_root' => $definition->publicRoot(),
                'engine_root_state' => $engineRootExists ? 'OK' : 'MISSING',
                'site_root_state' => $siteRootExists ? 'OK' : 'MISSING',
                'public_root_state' => $publicRootExists ? 'OK' : 'MISSING',
                'root_audit' => $this->rootAudit($engineRootExists, $siteRootExists, $publicRootExists),
                'fsm_state' => $fsmState,
                'health' => $health,
                'auth_profile' => $definition->authProfile(),
                'acl_profile' => $definition->aclProfile(),
                'routes_profile' => $definition->routesProfile(),
                'api_profile' => $definition->apiProfile(),
                'enabled' => $enabled,
                'is_current_host' => $this->normalizeHost($definition->host()) === $normalizedCurrentHost,
            ];
        }

        return new ServerOverviewSnapshot(gmdate('c'), $normalizedCurrentHost, $blocked > 0 ? 'SERVER_DEGRADED' : 'SERVER_READY', count($sites), $blocked, $sites);
    }

    private function normalizeHost(string $host): string
    {
        $trimmed = strtolower(trim($host));
        if ($trimmed === '') { return 'unknown-host'; }
        $withoutPort = preg_replace('/:\\d+$/', '', $trimmed);
        if (!is_string($withoutPort) || $withoutPort === '') { return $trimmed; }
        return $withoutPort;
    }

    private function rootAudit(bool $engineRootExists, bool $siteRootExists, bool $publicRootExists): string
    {
        return 'engine=' . ($engineRootExists ? 'OK' : 'MISSING') . '; site=' . ($siteRootExists ? 'OK' : 'MISSING') . '; public=' . ($publicRootExists ? 'OK' : 'MISSING');
    }
}
