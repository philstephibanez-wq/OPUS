<?php

declare(strict_types=1);

namespace Opus\Runtime;

use Opus\Admin\AdminBlockedStateViewModel;
use Opus\Http\PublicRequest;
use Opus\Security\PublicBlockedResponseRenderer;
use Opus\Security\PublicRouteControlPlane;
use RuntimeException;

final class AdminBlockedStateDashboardViewModelSmoke
{
    /** @return array<string,mixed> */
    public static function run(): array
    {
        $request = PublicRequest::get('/missing', 'opus-demo');
        $control = new PublicRouteControlPlane();
        $decision = $control->denyUnknownRoute($request);
        $event = $decision->blockedStateEvent();

        if ($event === null) {
            throw new RuntimeException('OPUS_ADMIN_BLOCKED_STATE_DASHBOARD_SMOKE_EVENT_MISSING');
        }

        $publicResponse = (new PublicBlockedResponseRenderer())->render($event);
        $adminViewModel = AdminBlockedStateViewModel::fromBlockedStateEvent($event)->toArray();

        $publicBody = $publicResponse->body();
        foreach (['PUBLIC_REQUEST_BLOCKED', 'UNKNOWN_PUBLIC_ROUTE', 'ADMIN_VIEW_BLOCKED_STATES', 'opus-demo', '/missing'] as $forbiddenLeak) {
            if (str_contains($publicBody, $forbiddenLeak)) {
                throw new RuntimeException('OPUS_PUBLIC_BLOCKED_RESPONSE_LEAKED_ADMIN_DETAIL: ' . $forbiddenLeak);
            }
        }

        foreach (['event_id', 'site', 'route_key', 'blocked_state', 'reason', 'admin_action', 'severity'] as $requiredAdminKey) {
            if (!array_key_exists($requiredAdminKey, $adminViewModel)) {
                throw new RuntimeException('OPUS_ADMIN_BLOCKED_STATE_VIEWMODEL_FIELD_MISSING: ' . $requiredAdminKey);
            }
        }

        return [
            'ok' => true,
            'gate' => 'P117A4_ADMIN_BLOCKED_STATE_DASHBOARD_VIEWMODEL',
            'public_status' => $publicResponse->statusCode(),
            'public_body' => $publicBody,
            'admin_surface' => $adminViewModel['surface'],
            'admin_event_id' => $adminViewModel['event_id'],
            'admin_blocked_state' => $adminViewModel['blocked_state'],
            'admin_reason' => $adminViewModel['reason'],
            'admin_action' => $adminViewModel['admin_action'],
            'admin_public_user_message_policy' => $adminViewModel['public_user_message_policy'],
        ];
    }
}
