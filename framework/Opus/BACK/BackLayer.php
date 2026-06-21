<?php
declare(strict_types=1);

namespace Opus\BACK;

/** OPUS BACK boundary: business and processing only. */
final class BackLayer
{
    public const CONTRACT = 'OPUS_BACK_LAYER_V1';
    public const RESPONSIBILITY = 'business-data-processing';

    /** @return list<string> */
    public static function applicationRoots(): array
    {
        return [
            'BACK/modules',
            'BACK/actions',
            'BACK/services',
            'BACK/repositories',
            'BACK/validators',
            'BACK/policies',
            'BACK/runners',
            'BACK/jobs',
            'BACK/workers',
            'BACK/adapters',
            'BACK/database',
            'BACK/dto',
            'BACK/viewmodels',
        ];
    }

    /** @return list<string> */
    public static function forbiddenFamilies(): array
    {
        return ['View', 'Layout', 'Section', 'Component', 'Renderer'];
    }
}
