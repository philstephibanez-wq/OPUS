<?php
declare(strict_types=1);

namespace Opus\COMMON;

/**
 * Shared OPUS language layer.
 *
 * COMMON is intentionally small. It may contain stable contracts, DTOs,
 * value objects, typed errors and generic results used across FRONT,
 * MIDDLE and BACK. It must not contain rendering, routing, security
 * policy, business logic, repositories, runners, jobs or database code.
 */
final class CommonLayer
{
    public const CONTRACT = 'OPUS_COMMON_LAYER_V1';
    public const RESPONSIBILITY = 'shared-language-only';

    /** @return list<string> */
    public static function allowedFamilies(): array
    {
        return [
            'Contract',
            'Dto',
            'ValueObject',
            'Error',
            'Result',
            'Enum',
            'Identifier',
            'Assertion',
            'Clock',
        ];
    }

    /** @return list<string> */
    public static function forbiddenFamilies(): array
    {
        return [
            'View',
            'Layout',
            'Section',
            'Component',
            'Renderer',
            'Router',
            'ApiGateway',
            'AccessControl',
            'Service',
            'Repository',
            'Runner',
            'Job',
            'Worker',
            'Database',
        ];
    }
}
