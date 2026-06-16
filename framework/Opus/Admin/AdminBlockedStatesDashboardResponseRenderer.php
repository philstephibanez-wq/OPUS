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

        $screen = AdminDashboardScreenStructure::blockedStates($viewModel)->toArray();

        return new AdminDashboardResponse(
            200,
            $this->renderBody($screen),
            [
                'Content-Type' => 'text/html; charset=utf-8',
                'X-OPUS-Admin-Surface' => 'admin_dashboard',
                'X-OPUS-Admin-Route' => 'blocked-states',
                'X-OPUS-Admin-Screen' => 'blocked-states',
            ]
        );
    }

    /** @param array{surface:string,route_key:string,screen_id:string,title:string,regions:list<string>,payload:array<string,mixed>} $screen */
    private function renderBody(array $screen): string
    {
        $payload = $screen['payload'];

        return '<!doctype html>' . "\n"
            . '<html lang="en">' . "\n"
            . '<head><meta charset="utf-8"><title>OPUS Admin</title></head>' . "\n"
            . '<body data-opus-surface="' . $this->e($screen['surface']) . '" data-opus-dashboard="blocked-states" data-opus-screen="' . $this->e($screen['screen_id']) . '">' . "\n"
            . '<header data-opus-region="admin_header">' . "\n"
            . '<h1>OPUS Admin Dashboard</h1>' . "\n"
            . '<p data-field="screen_title">' . $this->e($screen['title']) . '</p>' . "\n"
            . '</header>' . "\n"
            . '<main>' . "\n"
            . '<section data-opus-region="blocked_state_summary">' . "\n"
            . '<h2>Blocked state summary</h2>' . "\n"
            . '<p data-field="event_id">' . $this->e($payload['event_id'] ?? '') . '</p>' . "\n"
            . '<p data-field="blocked_state">' . $this->e($payload['blocked_state'] ?? '') . '</p>' . "\n"
            . '<p data-field="severity">' . $this->e($payload['severity'] ?? '') . '</p>' . "\n"
            . '</section>' . "\n"
            . '<section data-opus-region="blocked_state_detail">' . "\n"
            . '<h2>Diagnostic detail</h2>' . "\n"
            . '<p data-field="reason">' . $this->e($payload['reason'] ?? '') . '</p>' . "\n"
            . '<p data-field="admin_action">' . $this->e($payload['admin_action'] ?? '') . '</p>' . "\n"
            . '<p data-field="public_policy">' . $this->e($payload['public_user_message_policy'] ?? '') . '</p>' . "\n"
            . '</section>' . "\n"
            . '<section data-opus-region="recommended_actions">' . "\n"
            . '<h2>Recommended actions</h2>' . "\n"
            . $this->renderRecommendedActions($payload['recommended_actions'] ?? [])
            . '</section>' . "\n"
            . '</main>' . "\n"
            . '<footer data-opus-region="admin_audit_footer">' . "\n"
            . '<p data-field="route_key">' . $this->e($screen['route_key']) . '</p>' . "\n"
            . '</footer>' . "\n"
            . '</body>' . "\n"
            . '</html>';
    }

    private function renderRecommendedActions(mixed $actions): string
    {
        if (!is_array($actions)) {
            throw new RuntimeException('OPUS_ADMIN_DASHBOARD_ACTIONS_INVALID');
        }

        $html = '<ul>' . "\n";
        foreach ($actions as $action) {
            $html .= '<li data-field="recommended_action">' . $this->e($action) . '</li>' . "\n";
        }

        return $html . '</ul>' . "\n";
    }

    private function e(mixed $value): string
    {
        if (!is_scalar($value)) {
            throw new RuntimeException('OPUS_ADMIN_DASHBOARD_RENDER_VALUE_INVALID');
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
