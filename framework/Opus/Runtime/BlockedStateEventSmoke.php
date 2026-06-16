<?php

declare(strict_types=1);

namespace Opus\Runtime;

use Opus\Http\PublicRequest;
use Opus\Security\PublicBlockedResponseRenderer;
use Opus\Security\PublicRouteControlPlane;
use RuntimeException;

final class BlockedStateEventSmoke
{
    public static function run(): array
    {
        $request = PublicRequest::get('/missing', 'opus-demo');
        $control = new PublicRouteControlPlane();
        $decision = $control->denyUnknownRoute($request);
        $event = $decision->blockedStateEvent();

        if ($event === null) {
            throw new RuntimeException('OPUS_BLOCKED_STATE_EVENT_SMOKE_EVENT_MISSING');
        }

        $response = (new PublicBlockedResponseRenderer())->render($event);

        return [
            'ok' => true,
            'gate' => 'P117A3_FSM_BLOCKED_STATE_EVENT_MODEL',
            'public_status' => $response->statusCode(),
            'public_body' => $response->body(),
            'blocked_state_event' => $event->adminDiagnostics(),
        ];
    }
}
