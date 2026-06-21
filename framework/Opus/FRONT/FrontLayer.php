<?php
declare(strict_types=1);

namespace Opus\FRONT;

/** OPUS FRONT boundary: representation only. */
final class FrontLayer
{
    public const CONTRACT = 'OPUS_FRONT_LAYER_V1';
    public const RESPONSIBILITY = 'representation';

    /** @return list<string> */
    public static function applicationRoots(): array
    {
        return [
            'FRONT/views',
            'FRONT/layouts',
            'FRONT/sections',
            'FRONT/navigation',
            'FRONT/api-clients',
            'FRONT/custom-components',
            'FRONT/assets',
            'FRONT/theme',
        ];
    }

    /** @return list<string> */
    public static function forbiddenFamilies(): array
    {
        return ['Action', 'Service', 'Repository', 'Runner', 'Job', 'Worker', 'Database'];
    }
}
