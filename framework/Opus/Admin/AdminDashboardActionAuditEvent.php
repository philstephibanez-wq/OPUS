<?php

declare(strict_types=1);

namespace Opus\Admin;

use InvalidArgumentException;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Carry one protected OPUS administrator dashboard action audit event.
 *
 * Responsibility:
 *   Represent auditable action metadata without exposing it to public callers.
 *
 * Contract:
 *   Audit events are protected observability data. They must never be rendered in
 *   opaque public blocked responses.
 */
final class AdminDashboardActionAuditEvent
{
    /** @param array<string,mixed> $payload */
    private function __construct(private readonly array $payload)
    {
        foreach (['audit_event_id', 'source_decision_event_id', 'recorded_at_utc', 'surface', 'site', 'route_key', 'identity_context', 'action', 'decision', 'public_user_message_policy'] as $requiredKey) {
            if (!isset($this->payload[$requiredKey]) || !is_string($this->payload[$requiredKey]) || $this->payload[$requiredKey] === '') {
                throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_ACTION_AUDIT_EVENT_INVALID_' . strtoupper($requiredKey));
            }
        }
    }

    public static function fromDecision(AdminDashboardActionDecision $decision): self
    {
        $diagnostics = $decision->adminDiagnostics();
        $decisionKind = $decision->isGranted() ? 'ALLOW' : 'DENY';
        $reason = self::stringValue($diagnostics, 'reason', $decision->isGranted() ? 'ADMIN_DASHBOARD_ACTION_ALLOWED' : 'ADMIN_DASHBOARD_ACTION_DENIED');
        $effect = $decision->effect() ?? 'none';
        $sourceBlockedStateEventId = self::stringValue($diagnostics, 'source_blocked_state_event_id', $decision->blockedStateEvent()?->eventId() ?? 'none');
        $eventHash = strtoupper(substr(hash('sha256', $decision->eventId() . '|' . $decision->action() . '|' . $decisionKind . '|' . $reason . '|' . $effect), 0, 12));

        return new self([
            'audit_event_id' => 'OPUS-ADM-AUD-' . gmdate('Ymd') . '-' . $eventHash,
            'source_decision_event_id' => $decision->eventId(),
            'source_blocked_state_event_id' => $sourceBlockedStateEventId,
            'recorded_at_utc' => gmdate('c'),
            'surface' => self::stringValue($diagnostics, 'surface', 'admin_dashboard'),
            'site' => self::stringValue($diagnostics, 'site', 'unknown_site'),
            'route_key' => self::stringValue($diagnostics, 'route_key', 'unknown_route'),
            'identity_context' => self::stringValue($diagnostics, 'identity_context', 'unknown_identity'),
            'fsm_state' => self::stringValue($diagnostics, 'fsm_state', 'unknown_fsm_state'),
            'fsm_transition' => self::stringValue($diagnostics, 'fsm_transition', $decision->action()),
            'acl_policy' => self::stringValue($diagnostics, 'acl_policy', 'unknown_acl_policy'),
            'action' => $decision->action(),
            'decision' => $decisionKind,
            'reason' => $reason,
            'effect' => $effect,
            'public_user_message_policy' => 'opaque_support_only',
        ]);
    }

    public function auditEventId(): string
    {
        return $this->payload['audit_event_id'];
    }

    public function decision(): string
    {
        return $this->payload['decision'];
    }

    public function action(): string
    {
        return $this->payload['action'];
    }

    public function reason(): string
    {
        return $this->payload['reason'];
    }

    public function effect(): string
    {
        return $this->payload['effect'];
    }

    public function publicUserMessagePolicy(): string
    {
        return $this->payload['public_user_message_policy'];
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->payload;
    }

    /** @param array<string,mixed> $payload */
    private static function stringValue(array $payload, string $key, string $default): string
    {
        $value = $payload[$key] ?? null;
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $default;
    }
}
