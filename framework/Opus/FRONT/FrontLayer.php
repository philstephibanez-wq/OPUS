<?php
declare(strict_types=1);

namespace Opus\FRONT;

final class FrontLayer
{
    public const CONTRACT = 'OPUS_FRONT_LAYER_V1';
    public const RESPONSIBILITY = 'representation';

    public static function applicationRoots(): array
    {
        return ['frontend/views', 'frontend/layouts', 'frontend/sections', 'frontend/navigation', 'frontend/api-clients', 'frontend/custom-components', 'frontend/assets', 'frontend/theme'];
    }
}
