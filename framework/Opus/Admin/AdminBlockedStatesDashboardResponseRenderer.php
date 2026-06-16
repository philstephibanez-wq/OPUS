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
            . '<head>' . "\n"
            . '<meta charset="utf-8">' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
            . '<title>OPUS Admin</title>' . "\n"
            . '<style data-opus-admin-style="native">' . $this->renderStyles() . '</style>' . "\n"
            . '</head>' . "\n"
            . '<body data-opus-surface="' . $this->e($screen['surface']) . '" data-opus-dashboard="blocked-states" data-opus-screen="' . $this->e($screen['screen_id']) . '">' . "\n"
            . '<div class="opus-admin-shell">' . "\n"
            . '<header class="opus-admin-hero" data-opus-region="admin_header">' . "\n"
            . '<div class="opus-admin-brandline"><span class="opus-admin-mark">OPUS</span><span>Native administration</span></div>' . "\n"
            . '<div class="opus-admin-hero-grid">' . "\n"
            . '<div><h1>OPUS Admin Dashboard</h1><p data-field="screen_title">' . $this->e($screen['title']) . '</p></div>' . "\n"
            . '<div class="opus-admin-route-pill" data-field="route_key">' . $this->e($screen['route_key']) . '</div>' . "\n"
            . '</div>' . "\n"
            . '</header>' . "\n"
            . '<main class="opus-admin-main">' . "\n"
            . '<section class="opus-admin-card opus-admin-card--summary" data-opus-region="blocked_state_summary">' . "\n"
            . '<div class="opus-admin-card-heading"><span class="opus-admin-kicker">Current gate</span><h2>Blocked state summary</h2></div>' . "\n"
            . '<div class="opus-admin-summary-grid">' . "\n"
            . $this->renderMetric('Event', 'event_id', $payload['event_id'] ?? '')
            . $this->renderMetric('State', 'blocked_state', $payload['blocked_state'] ?? '')
            . $this->renderMetric('Severity', 'severity', $payload['severity'] ?? '')
            . '</div>' . "\n"
            . '</section>' . "\n"
            . '<section class="opus-admin-card" data-opus-region="blocked_state_detail">' . "\n"
            . '<div class="opus-admin-card-heading"><span class="opus-admin-kicker">Internal diagnostics</span><h2>Diagnostic detail</h2></div>' . "\n"
            . '<dl class="opus-admin-detail-list">' . "\n"
            . $this->renderDetail('Reason', 'reason', $payload['reason'] ?? '')
            . $this->renderDetail('Admin action', 'admin_action', $payload['admin_action'] ?? '')
            . $this->renderDetail('Public policy', 'public_policy', $payload['public_user_message_policy'] ?? '')
            . '</dl>' . "\n"
            . '</section>' . "\n"
            . '<section class="opus-admin-card" data-opus-region="recommended_actions">' . "\n"
            . '<div class="opus-admin-card-heading"><span class="opus-admin-kicker">Next controlled actions</span><h2>Recommended actions</h2></div>' . "\n"
            . $this->renderRecommendedActions($payload['recommended_actions'] ?? [])
            . '</section>' . "\n"
            . '</main>' . "\n"
            . '<footer class="opus-admin-footer" data-opus-region="admin_audit_footer">' . "\n"
            . '<span>OPUS native dashboard</span><span data-field="route_key">' . $this->e($screen['route_key']) . '</span>' . "\n"
            . '</footer>' . "\n"
            . '</div>' . "\n"
            . '</body>' . "\n"
            . '</html>';
    }

    private function renderMetric(string $label, string $field, mixed $value): string
    {
        return '<article class="opus-admin-metric">'
            . '<span>' . $this->e($label) . '</span>'
            . '<strong data-field="' . $this->e($field) . '">' . $this->e($value) . '</strong>'
            . '</article>' . "\n";
    }

    private function renderDetail(string $label, string $field, mixed $value): string
    {
        return '<div class="opus-admin-detail-row">'
            . '<dt>' . $this->e($label) . '</dt>'
            . '<dd data-field="' . $this->e($field) . '">' . $this->e($value) . '</dd>'
            . '</div>' . "\n";
    }

    private function renderRecommendedActions(mixed $actions): string
    {
        if (!is_array($actions)) {
            throw new RuntimeException('OPUS_ADMIN_DASHBOARD_ACTIONS_INVALID');
        }

        $html = '<ul class="opus-admin-action-list">' . "\n";
        foreach ($actions as $action) {
            $html .= '<li data-field="recommended_action"><span>' . $this->e($action) . '</span></li>' . "\n";
        }

        return $html . '</ul>' . "\n";
    }

    private function renderStyles(): string
    {
        return <<<CSS
:root{color-scheme:dark;--bg:#0d1117;--panel:#151b23;--panel2:#1d2633;--line:#303847;--text:#f2f6fb;--muted:#9aa7b6;--accent:#73daca;--warn:#f2cc60;--danger:#ff7b72;--shadow:0 24px 60px rgba(0,0,0,.35)}*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,Segoe UI,Roboto,Arial,sans-serif;background:radial-gradient(circle at top left,#1d2a3b 0,#0d1117 34rem),var(--bg);color:var(--text)}.opus-admin-shell{width:min(1180px,calc(100% - 48px));margin:0 auto;padding:32px 0 28px}.opus-admin-hero{border:1px solid var(--line);border-radius:24px;background:linear-gradient(135deg,rgba(115,218,202,.16),rgba(29,38,51,.88));box-shadow:var(--shadow);padding:28px}.opus-admin-brandline{display:flex;gap:12px;align-items:center;color:var(--muted);font-size:13px;text-transform:uppercase;letter-spacing:.12em}.opus-admin-mark{display:inline-flex;align-items:center;justify-content:center;width:48px;height:28px;border-radius:999px;background:var(--accent);color:#081018;font-weight:800;letter-spacing:.08em}.opus-admin-hero-grid{display:grid;grid-template-columns:1fr auto;gap:24px;align-items:end;margin-top:22px}.opus-admin-hero h1{margin:0;font-size:clamp(34px,5vw,58px);line-height:.96}.opus-admin-hero p{margin:12px 0 0;color:var(--muted);font-size:18px}.opus-admin-route-pill{border:1px solid rgba(115,218,202,.5);background:rgba(115,218,202,.12);border-radius:999px;padding:12px 16px;color:var(--accent);font-weight:700}.opus-admin-main{display:grid;grid-template-columns:1.2fr .8fr;gap:18px;margin-top:18px}.opus-admin-card{border:1px solid var(--line);border-radius:22px;background:linear-gradient(180deg,rgba(21,27,35,.96),rgba(13,17,23,.96));box-shadow:0 16px 40px rgba(0,0,0,.22);padding:22px}.opus-admin-card--summary{grid-column:1/-1}.opus-admin-card-heading{display:flex;flex-direction:column;gap:6px;margin-bottom:18px}.opus-admin-kicker{color:var(--accent);font-size:12px;text-transform:uppercase;letter-spacing:.14em;font-weight:800}.opus-admin-card h2{margin:0;font-size:24px}.opus-admin-summary-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.opus-admin-metric{border:1px solid var(--line);border-radius:18px;background:var(--panel2);padding:16px;min-height:104px}.opus-admin-metric span{display:block;color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.12em}.opus-admin-metric strong{display:block;margin-top:12px;font-size:18px;line-height:1.25;word-break:break-word}.opus-admin-detail-list{display:grid;gap:12px;margin:0}.opus-admin-detail-row{border-left:3px solid var(--warn);background:rgba(242,204,96,.08);border-radius:14px;padding:14px}.opus-admin-detail-row dt{color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.12em}.opus-admin-detail-row dd{margin:8px 0 0;font-weight:700;word-break:break-word}.opus-admin-action-list{list-style:none;margin:0;padding:0;display:grid;gap:12px}.opus-admin-action-list li{border:1px solid rgba(115,218,202,.35);background:rgba(115,218,202,.08);border-radius:16px;padding:14px 16px;color:var(--text);font-weight:700}.opus-admin-action-list li::before{content:'→';color:var(--accent);font-weight:900;margin-right:10px}.opus-admin-footer{display:flex;justify-content:space-between;gap:18px;margin-top:18px;padding:14px 4px;color:var(--muted);font-size:13px}@media (max-width:760px){.opus-admin-shell{width:min(100% - 28px,1180px);padding-top:18px}.opus-admin-hero-grid,.opus-admin-main,.opus-admin-summary-grid{grid-template-columns:1fr}.opus-admin-route-pill{width:max-content}.opus-admin-footer{flex-direction:column}}
CSS;
    }

    private function e(mixed $value): string
    {
        if (!is_scalar($value)) {
            throw new RuntimeException('OPUS_ADMIN_DASHBOARD_RENDER_VALUE_INVALID');
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
