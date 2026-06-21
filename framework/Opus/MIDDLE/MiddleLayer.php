<?php
declare(strict_types=1);

namespace Opus\MIDDLE;

final class MiddleLayer
{
    public const CONTRACT = 'OPUS_MIDDLE_LAYER_V1';
    public const RESPONSIBILITY = 'routing-transport-security-orchestration';
    public const PROCESSOR = 'FSM';

    public static function applicationRoots(): array
    {
        return ['MIDDLE/routes', 'MIDDLE/api', 'MIDDLE/security', 'MIDDLE/contracts', 'MIDDLE/fsm', 'MIDDLE/audit', 'MIDDLE/transport'];
    }

    public static function mandatoryGates(): array
    {
        return ['route', 'request-contract', 'access-control', 'identity', 'fsm', 'audit'];
    }
}
