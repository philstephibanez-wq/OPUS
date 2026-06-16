<?php

declare(strict_types=1);

namespace Opus\Admin;

use InvalidArgumentException;
use Opus\Security\BlockedStateEvent;

/**
 * PUBLIC VIEW MODEL
 *
 * Role:
 *   Represent administrator-only diagnostics for a blocked FSM state event.
 *
 * Responsibility:
 *   Prepare dashboard data for authorized administrators without changing the
 *   public response opacity contract.
 *
 * Contract:
 *   This view model is admin-only. It must never be serialized to a public
 *   response. The public user receives only the neutral support message from
 *   PublicBlockedResponseRenderer.
 */
final class AdminBlockedStateViewModel
{
    /** @param array<string,mixed> $payload */
    private function __construct(private readonly array $payload)
    {
        foreach (['surface', 'kind', 'event_id', 'blocked_state', 'reason', 'admin_action'] as $requiredKey) {
            if (!array_key_exists($requiredKey, $this->payload)) {
                throw new InvalidArgumentException('OPUS_ADMIN_BLOCKED_STATE_VIEWMODEL_KEY_MISSING: ' . $requiredKey);
            }

            if (!is_string($this->payload[$requiredKey]) || trim($this->payload[$requiredKey]) === '') {
                throw new InvalidArgumentException('OPUS_ADMIN_BLOCKED_STATE_VIEWMODEL_KEY_INVALID: ' . $requiredKey);
            }
        }
    }

    public static function fromBlockedStateEvent(BlockedStateEvent $event): self
    {
        $diagnostics = $event->adminDiagnostics();

        return new self([
            'surface' => 'admin_dashboard',
            'kind' => 'blocked_state_event',
            'event_id' => $diagnostics['event_id'],
            'site' => $diagnostics['site'],
            'route_key' => $diagnostics['route_key'],
            'blocked_state' => $diagnostics['blocked_state'],
            'reason' => $diagnostics['reason'],
            'severity' => $diagnostics['severity'],
            'admin_action' => $diagnostics['admin_action'],
            'operator_summary' => 'A public request was blocked by the OPUS FSM bastion control plane.',
            'recommended_actions' => [
                $diagnostics['admin_action'],
                'ADMIN_REVIEW_PUBLIC_ERROR_OPACITY',
                'ADMIN_ACKNOWLEDGE_BLOCKED_STATE_EVENT',
            ],
            'public_user_message_policy' => 'opaque_support_only',
        ]);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->payload;
    }
}
