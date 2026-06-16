<?php

declare(strict_types=1);

namespace Opus\Server;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Build the read-only OPUS server supervision snapshot.
 *
 * Responsibility:
 *   Evaluate declared site configuration against local runtime facts that are
 *   safe for an administrator dashboard.
 *
 * Contract:
 *   This service does not mutate server state, Apache configuration, sessions,
 *   ACL, tokens or FSM states. It prepares observations only.
 */
final class ServerSiteSupervisor
{
    public function __construct(private readonly ServerSiteRegistry $registry)
    {
    }

    public function snapshot(string $currentHost): ServerOverviewSnapshot
    {
        $normalizedCurrentHost = $this->normalizeHost($currentHost);
        $sites = [];
        $blocked = 0;

        foreach ($this->registry->sites() as $definition) {
            $publicRootExists = is_dir($definition->publicRoot());
            $fsmState = $publicRootExists ? $definition->expectedFsmState() : 'SITE_BLOCKED';
            $health = $publicRootExists ? 'OK' : 'BLOCKED';

            if ($health !== 'OK') {
                ++$blocked;
            }

            $sites[] = [
                'id' => $definition->id(),
                'label' => $definition->label(),
                'host' => $definition->host(),
                'public_root' => $definition->publicRoot(),
                'fsm_state' => $fsmState,
                'health' => $health,
                'auth_profile' => $definition->authProfile(),
                'acl_profile' => $definition->aclProfile(),
                'is_current_host' => $this->normalizeHost($definition->host()) === $normalizedCurrentHost,
            ];
        }

        return new ServerOverviewSnapshot(
            gmdate('c'),
            $normalizedCurrentHost,
            $blocked > 0 ? 'SERVER_DEGRADED' : 'SERVER_READY',
            count($sites),
            $blocked,
            $sites
        );
    }

    private function normalizeHost(string $host): string
    {
        $trimmed = strtolower(trim($host));
        if ($trimmed === '') {
            return 'unknown-host';
        }

        $withoutPort = preg_replace('/:\d+$/', '', $trimmed);
        if (!is_string($withoutPort) || $withoutPort === '') {
            return $trimmed;
        }

        return $withoutPort;
    }
}