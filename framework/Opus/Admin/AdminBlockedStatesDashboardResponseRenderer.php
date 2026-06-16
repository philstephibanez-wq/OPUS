<?php

declare(strict_types=1);

namespace Opus\Admin;

use Opus\Http\PublicResponse;
use RuntimeException;

final class AdminBlockedStatesDashboardResponseRenderer
{
    public function render(AdminRouteControlDecision $decision): AdminDashboardResponse|PublicResponse
    {
        if (!$decision->isAllowed()) {
            $publicResponse = $decision->publicResponse();
            if ($publicResponse === null) {
                throw new RuntimeException('OPUS_ADMIN_DASHBOARD_DENIED_PUBLIC_RESPONSE_MISSING');
            }

            return $publicResponse;
        }

        $viewModel = $decision->adminViewModel();
        if ($viewModel === null) {
            throw new RuntimeException('OPUS_ADMIN_DASHBOARD_ALLOWED_VIEWMODEL_MISSING');
        }

        $payload = $viewModel->toArray();

        return new AdminDashboardResponse(
            200,
            $this->renderBody($payload),
            [
                'Content-Type' => 'text/html; charset=utf-8',
                'X-OPUS-Admin-Surface' => 'admin_dashboard',
                'X-OPUS-Admin-Route' => 'blocked-states',
            ]
        );
    }

    /** @param array<string,mixed> $payload */
    private function renderBody(array $payload): string
    {
        return '<!doctype html>' . "\n"
            . '<html lang="en">' . "\n"
            . '<head><meta charset="utf-8"><title>OPUS Admin</title></head>' . "\n"
            . '<body data-opus-surface="admin_dashboard" data-opus-dashboard="blocked-states">' . "\n"
            . '<main>' . "\n"
            . '<h1>OPUS Admin Dashboard</h1>' . "\n"
            . '<p data-field="event_id">' . $this->e($payload['event_id'] ?? '') . '</p>' . "\n"
            . '<p data-field="blocked_state">' . $this->e($payload['blocked_state'] ?? '') . '</p>' . "\n"
            . '<p data-field="reason">' . $this->e($payload['reason'] ?? '') . '</p>' . "\n"
            . '<p data-field="admin_action">' . $this->e($payload['admin_action'] ?? '') . '</p>' . "\n"
            . '<p data-field="public_policy">' . $this->e($payload['public_user_message_policy'] ?? '') . '</p>' . "\n"
            . '</main>' . "\n"
            . '</body>' . "\n"
            . '</html>';
    }

    private function e(mixed $value): string
    {
        if (!is_scalar($value)) {
            throw new RuntimeException('OPUS_ADMIN_DASHBOARD_RENDER_VALUE_INVALID');
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
