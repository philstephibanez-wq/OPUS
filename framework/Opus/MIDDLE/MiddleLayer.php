<?php
declare(strict_types=1);

namespace Opus\MIDDLE;

final class MiddleLayer
{
    public const CONTRACT = 'OPUS_MIDDLE_LAYER_V1';
    public const RESPONSIBILITY = 'routing-transport-security';
    public const PROCESSOR = 'FSM';

    public static function applicationRoots(): array
    {
        return ['middle/routes', 'middle/api', 'middle/security', 'middle/contracts', 'middle/fsm'];
    }
}
