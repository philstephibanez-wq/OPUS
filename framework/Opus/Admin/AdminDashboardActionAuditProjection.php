<?php

declare(strict_types=1);

namespace Opus\Admin;

use InvalidArgumentException;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Build an administrator-only projection of protected dashboard action audit
 *   events.
 *
 * Responsibility:
 *   Convert audit trail entries into a stable admin dashboard payload without
 *   authorizing actions, mutating the audit trail, or changing public response
 *   opacity.
 *
 * Contract:
 *   This projection is protected administrator observability data. It must never
 *   be serialized into an opaque public blocked response.
 */
final class AdminDashboardActionAuditProjection
{
    public const SURFACE = 'admin_dashboard';
    public const KIND = 'action_audit_projection';

    /** @param array<string,mixed> $payload */
    private function __construct(private readonly array $payload)
    {
        foreach (['surface', 'kind', 'public_user_message_policy'] as $requiredKey) {
            if (!isset($this->payload[$requiredKey]) || !is_string($this->payload[$requiredKey]) || $this->payload[$requiredKey] === '') {
                throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_ACTION_AUDIT_PROJECTION_INVALID_' . strtoupper($requiredKey));
            }
        }

        if ($this->payload['surface'] !== self::SURFACE) {
            throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_ACTION_AUDIT_PROJECTION_SURFACE_INVALID');
        }

        if ($this->payload['kind'] !== self::KIND) {
            throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_ACTION_AUDIT_PROJECTION_KIND_INVALID');
        }

        if (!isset($this->payload['audit_event_count']) || !is_int($this->payload['audit_event_count']) || $this->payload['audit_event_count'] < 1) {
            throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_ACTION_AUDIT_PROJECTION_COUNT_INVALID');
        }

        if (!isset($this->payload['events']) || !is_array($this->payload['events'])) {
            throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_ACTION_AUDIT_PROJECTION_EVENTS_INVALID');
        }

        if ($this->payload['audit_event_count'] !== count($this->payload['events'])) {
            throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_ACTION_AUDIT_PROJECTION_COUNT_MISMATCH');
        }
    }

    public static function fromAuditTrail(AdminDashboardActionAuditTrail $trail): self
    {
        $events = $trail->events();
        if ($events === []) {
            throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_ACTION_AUDIT_PROJECTION_TRAIL_EMPTY');
        }

        $rows = [];
        foreach ($events as $event) {
            $row = $event->toArray();
            $rows[] = [
                'audit_event_id' => self::stringValue($row, 'audit_event_id'),
                'decision' => self::stringValue($row, 'decision'),
                'action' => self::stringValue($row, 'action'),
                'reason' => self::stringValue($row, 'reason'),
                'effect' => self::stringValue($row, 'effect'),
                'public_user_message_policy' => self::stringValue($row, 'public_user_message_policy'),
            ];
        }

        return new self([
            'surface' => self::SURFACE,
            'kind' => self::KIND,
            'audit_event_count' => count($rows),
            'events' => $rows,
            'decisions' => array_map(static fn (array $row): string => $row['decision'], $rows),
            'actions' => array_map(static fn (array $row): string => $row['action'], $rows),
            'public_user_message_policy' => 'admin_only_no_public_serialisation',
        ]);
    }

    public function count(): int
    {
        return $this->payload['audit_event_count'];
    }

    /** @return list<string> */
    public function decisions(): array
    {
        return $this->payload['decisions'];
    }

    /** @return list<string> */
    public function actions(): array
    {
        return $this->payload['actions'];
    }

    /** @return list<array<string,string>> */
    public function events(): array
    {
        return $this->payload['events'];
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->payload;
    }

    /** @param array<string,mixed> $payload */
    private static function stringValue(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException('OPUS_ADMIN_DASHBOARD_ACTION_AUDIT_PROJECTION_VALUE_INVALID_' . strtoupper($key));
        }

        return $value;
    }
}
