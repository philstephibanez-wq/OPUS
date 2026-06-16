<?php

declare(strict_types=1);

namespace Opus\Admin;

use InvalidArgumentException;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Describe the stable screen structure of a native OPUS administrator dashboard.
 *
 * Responsibility:
 *   Provide explicit dashboard regions around administrator-only ViewModel data,
 *   without changing or weakening the public error opacity contract.
 *
 * Contract:
 *   This structure is admin-only. It is produced only after the admin route
 *   control plane has allowed access, and it must never be serialized into a
 *   public blocked response.
 */
final class AdminDashboardScreenStructure
{
    public const SURFACE = 'admin_dashboard';
    public const BLOCKED_STATES_ROUTE_KEY = 'blocked-states';
    public const BLOCKED_STATES_SCREEN_ID = 'blocked-states';

    private const REQUIRED_REGIONS = [
        'admin_header',
        'blocked_state_summary',
        'blocked_state_detail',
        'recommended_actions',
        'admin_audit_footer',
    ];

    /**
     * @param list<string> $regions
     * @param array<string,mixed> $payload
     */
    private function __construct(
        private readonly string $surface,
        private readonly string $routeKey,
        private readonly string $screenId,
        private readonly string $title,
        private readonly array $regions,
        private readonly array $payload
    ) {
        foreach ([$this->surface, $this->routeKey, $this->screenId, $this->title] as $value) {
            if ($value === '') {
                throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_SCREEN_STRUCTURE_TEXT_EMPTY');
            }
        }

        if ($this->regions !== self::REQUIRED_REGIONS) {
            throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_SCREEN_STRUCTURE_REGIONS_INVALID');
        }

        foreach (['event_id', 'blocked_state', 'reason', 'admin_action', 'recommended_actions'] as $requiredKey) {
            if (!array_key_exists($requiredKey, $this->payload)) {
                throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_SCREEN_STRUCTURE_PAYLOAD_KEY_MISSING: ' . $requiredKey);
            }
        }
    }

    public static function blockedStates(AdminBlockedStateViewModel $viewModel): self
    {
        return new self(
            self::SURFACE,
            self::BLOCKED_STATES_ROUTE_KEY,
            self::BLOCKED_STATES_SCREEN_ID,
            'Blocked states',
            self::REQUIRED_REGIONS,
            $viewModel->toArray()
        );
    }

    /** @return array{surface:string,route_key:string,screen_id:string,title:string,regions:list<string>,payload:array<string,mixed>} */
    public function toArray(): array
    {
        return [
            'surface' => $this->surface,
            'route_key' => $this->routeKey,
            'screen_id' => $this->screenId,
            'title' => $this->title,
            'regions' => $this->regions,
            'payload' => $this->payload,
        ];
    }
}
