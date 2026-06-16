<?php

declare(strict_types=1);

namespace Opus\Admin;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Record protected OPUS administrator dashboard action audit events.
 *
 * Responsibility:
 *   Convert admin dashboard action control decisions into protected audit trail
 *   entries for internal operations surfaces.
 *
 * Contract:
 *   This service does not authorize actions and does not publish audit payloads to
 *   public responses.
 */
final class AdminDashboardActionAuditTrail
{
    /** @var list<AdminDashboardActionAuditEvent> */
    private array $events = [];

    public function record(AdminDashboardActionDecision $decision): AdminDashboardActionAuditEvent
    {
        $event = AdminDashboardActionAuditEvent::fromDecision($decision);
        $this->events[] = $event;

        return $event;
    }

    /** @return list<AdminDashboardActionAuditEvent> */
    public function events(): array
    {
        return $this->events;
    }

    public function count(): int
    {
        return count($this->events);
    }
}
