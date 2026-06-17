<?php

declare(strict_types=1);

namespace Opus\Admin;

use Opus\Server\ServerOverviewSnapshot;
use RuntimeException;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Render the native OPUS multi-site server dashboard.
 *
 * Contract:
 *   This renderer is admin-only. It may render internal site names and local
 *   paths only after AdminServerOverviewAccessControlPlane has allowed access.
 */
final class AdminServerOverviewDashboardResponseRenderer
{
    public function render(AdminServerOverviewAccessDecision $decision, ServerOverviewSnapshot $snapshot): AdminDashboardResponse
    {
        if (!$decision->isAllowed()) { throw new RuntimeException('OPUS_ADMIN_SERVER_OVERVIEW_RENDER_DENIED_DECISION'); }
        return new AdminDashboardResponse(200, $this->renderBody($decision, $snapshot), ['Content-Type' => 'text/html; charset=utf-8','X-OPUS-Admin-Surface' => 'server_control_plane','X-OPUS-Admin-Route' => 'server-overview','X-OPUS-Admin-Screen' => 'server-overview']);
    }

    private function renderBody(AdminServerOverviewAccessDecision $decision, ServerOverviewSnapshot $snapshot): string
    {
        return '<!doctype html>\n<html lang="en">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width, initial-scale=1">\n<title>OPUS Server Control Plane</title>\n<style data-opus-admin-style="native-server">' . $this->renderStyles() . '</style>\n</head>\n'
            . '<body data-opus-surface="server_control_plane" data-opus-dashboard="server-overview"><div class="opus-admin-shell">'
            . '<header class="opus-admin-hero" data-opus-region="admin_header"><div class="opus-admin-brandline"><span class="opus-admin-mark">OPUS</span><span>Server control plane</span></div><div class="opus-admin-hero-grid"><div><h1>OPUS Server Control Plane</h1><p>Read-only supervision of every declared OPUS site on this server.</p></div><div class="opus-admin-route-pill" data-field="server_state">' . $this->e($snapshot->serverState()) . '</div></div></header>'
            . '<main class="opus-admin-main"><section class="opus-admin-card opus-admin-card--summary" data-opus-region="server_summary"><div class="opus-admin-card-heading"><span class="opus-admin-kicker">Server overview</span><h2>Declared OPUS sites</h2></div><div class="opus-admin-summary-grid">'
            . $this->renderMetric('Current host', 'current_host', $snapshot->currentHost()) . $this->renderMetric('Sites', 'site_count', (string) $snapshot->siteCount()) . $this->renderMetric('Blocked', 'blocked_site_count', (string) $snapshot->blockedSiteCount())
            . '</div></section><section class="opus-admin-card opus-admin-card--wide" data-opus-region="server_sites"><div class="opus-admin-card-heading"><span class="opus-admin-kicker">Multi-site supervision</span><h2>Sites on this OPUS server</h2></div>' . $this->renderSites($snapshot) . '</section>'
            . '<section class="opus-admin-card" data-opus-region="server_security"><div class="opus-admin-card-heading"><span class="opus-admin-kicker">Security gate</span><h2>FSM + ACL bootstrap</h2></div><dl class="opus-admin-detail-list">' . $this->renderDetail('Decision event', 'event_id', $decision->eventId()) . $this->renderDetail('FSM state', 'fsm_state', $decision->diagnostics()['fsm_state'] ?? '') . $this->renderDetail('ACL policy', 'acl_policy', $decision->diagnostics()['acl_policy'] ?? '') . '</dl></section></main>'
            . '<footer class="opus-admin-footer" data-opus-region="admin_audit_footer"><span>Generated ' . $this->e($snapshot->generatedAt()) . '</span><span>admin-only server overview</span></footer></div></body></html>';
    }

    private function renderSites(ServerOverviewSnapshot $snapshot): string
    {
        $html = '<div class="opus-site-grid">\n';
        foreach ($snapshot->sites() as $site) {
            $classes = 'opus-site-card';
            if (($site['is_current_host'] ?? false) === true) { $classes .= ' opus-site-card--current'; }
            $healthClass = 'opus-site-health--' . strtolower((string) $site['health']);
            $html .= '<article class="' . $this->e($classes) . '" data-site-id="' . $this->e($site['id']) . '" data-site-type="' . $this->e($site['site_type']) . '">'
                . '<div class="opus-site-card-head"><span>' . $this->e($site['label']) . '</span><strong class="' . $this->e($healthClass) . '" data-field="health">' . $this->e($site['health']) . '</strong></div><dl>'
                . $this->renderDetail('Enabled', 'enabled', $site['enabled'] ? 'true' : 'false')
                . $this->renderDetail('Host', 'host', $site['host']) . $this->renderDetail('Site type', 'site_type', $site['site_type']) . $this->renderDetail('FSM', 'fsm_state', $site['fsm_state'])
                . $this->renderDetail('Auth', 'auth_profile', $site['auth_profile']) . $this->renderDetail('ACL', 'acl_profile', $site['acl_profile']) . $this->renderDetail('Routes', 'routes_profile', $site['routes_profile']) . $this->renderDetail('API', 'api_profile', $site['api_profile'])
                . $this->renderDetail('Engine root', 'engine_root', $site['engine_root']) . $this->renderDetail('Site root', 'site_root', $site['site_root']) . $this->renderDetail('Public root', 'public_root', $site['public_root']) . $this->renderDetail('Root audit', 'root_audit', $site['root_audit'])
                . '</dl></article>\n';
        }
        return $html . '</div>\n';
    }

    private function renderMetric(string $label, string $field, string $value): string
    {
        return '<article class="opus-admin-metric"><span>' . $this->e($label) . '</span><strong data-field="' . $this->e($field) . '">' . $this->e($value) . '</strong></article>';
    }

    private function renderDetail(string $label, string $field, mixed $value): string
    {
        return '<div class="opus-admin-detail-row"><dt>' . $this->e($label) . '</dt><dd data-field="' . $this->e($field) . '">' . $this->e($value) . '</dd></div>';
    }

    private function renderStyles(): string
    {
        return ':root{color-scheme:dark;--bg:#0d1117;--panel:#151b23;--panel2:#1d2633;--line:#303847;--text:#f2f6fb;--muted:#9aa7b6;--accent:#73daca;--warn:#f2cc60;--danger:#ff7b72;--ok:#8ddb8c;--shadow:0 24px 60px rgba(0,0,0,.35)}*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,Segoe UI,Roboto,Arial,sans-serif;background:radial-gradient(circle at top left,#1d2a3b 0,#0d1117 34rem),var(--bg);color:var(--text)}.opus-admin-shell{width:min(1320px,calc(100% - 48px));margin:0 auto;padding:32px 0 28px}.opus-admin-hero{border:1px solid var(--line);border-radius:24px;background:linear-gradient(135deg,rgba(115,218,202,.16),rgba(29,38,51,.88));box-shadow:var(--shadow);padding:28px}.opus-admin-brandline{display:flex;gap:12px;align-items:center;color:var(--muted);font-size:13px;text-transform:uppercase;letter-spacing:.12em}.opus-admin-mark{display:inline-flex;align-items:center;justify-content:center;width:48px;height:28px;border-radius:999px;background:var(--accent);color:#081018;font-weight:800;letter-spacing:.08em}.opus-admin-hero-grid{display:grid;grid-template-columns:1fr auto;gap:24px;align-items:end;margin-top:22px}.opus-admin-hero h1{margin:0;font-size:clamp(34px,5vw,58px);line-height:.96}.opus-admin-hero p{margin:12px 0 0;color:var(--muted);font-size:18px}.opus-admin-route-pill{border:1px solid rgba(115,218,202,.5);background:rgba(115,218,202,.12);border-radius:999px;padding:12px 16px;color:var(--accent);font-weight:700}.opus-admin-main{display:grid;grid-template-columns:1.35fr .65fr;gap:18px;margin-top:18px}.opus-admin-card{border:1px solid var(--line);border-radius:22px;background:linear-gradient(180deg,rgba(21,27,35,.96),rgba(13,17,23,.96));box-shadow:0 16px 40px rgba(0,0,0,.22);padding:22px}.opus-admin-card--summary,.opus-admin-card--wide{grid-column:1/-1}.opus-admin-card-heading{display:flex;flex-direction:column;gap:6px;margin-bottom:18px}.opus-admin-kicker{color:var(--accent);font-size:12px;text-transform:uppercase;letter-spacing:.14em;font-weight:800}.opus-admin-card h2{margin:0;font-size:24px}.opus-admin-summary-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.opus-admin-metric{border:1px solid var(--line);border-radius:18px;background:var(--panel2);padding:16px;min-height:104px}.opus-admin-metric span{display:block;color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.12em}.opus-admin-metric strong{display:block;margin-top:12px;font-size:18px;line-height:1.25;word-break:break-word}.opus-site-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.opus-site-card{border:1px solid var(--line);border-radius:18px;background:rgba(29,38,51,.72);padding:16px}.opus-site-card--current{border-color:rgba(115,218,202,.8);box-shadow:0 0 0 1px rgba(115,218,202,.2) inset}.opus-site-card-head{display:flex;justify-content:space-between;gap:14px;align-items:center;margin-bottom:14px}.opus-site-card-head span{font-weight:800}.opus-site-card-head strong{border-radius:999px;padding:6px 10px;font-size:12px}.opus-site-health--ok{background:rgba(141,219,140,.12);color:var(--ok)}.opus-site-health--blocked{background:rgba(255,123,114,.13);color:var(--danger)}.opus-site-health--disabled{background:rgba(154,167,182,.14);color:var(--muted)}.opus-admin-detail-list,.opus-site-card dl{display:grid;gap:10px;margin:0}.opus-admin-detail-row{border-left:3px solid var(--warn);background:rgba(242,204,96,.08);border-radius:14px;padding:12px}.opus-admin-detail-row dt{color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.12em}.opus-admin-detail-row dd{margin:7px 0 0;font-weight:700;word-break:break-word}.opus-admin-footer{display:flex;justify-content:space-between;gap:18px;margin-top:18px;padding:14px 4px;color:var(--muted);font-size:13px}@media (max-width:860px){.opus-admin-shell{width:min(100% - 28px,1320px);padding-top:18px}.opus-admin-hero-grid,.opus-admin-main,.opus-admin-summary-grid,.opus-site-grid{grid-template-columns:1fr}.opus-admin-route-pill{width:max-content}.opus-admin-footer{flex-direction:column}}';
    }

    private function e(mixed $value): string
    {
        if (!is_scalar($value)) { throw new RuntimeException('OPUS_ADMIN_SERVER_OVERVIEW_RENDER_VALUE_INVALID'); }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
