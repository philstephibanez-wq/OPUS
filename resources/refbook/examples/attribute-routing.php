<?php
declare(strict_types=1);

/*
 * Opus RefBook example: attribute routing.
 *
 * Purpose:
 *   Show how an explicit route can be declared with metadata.
 */

use ASAP\Routing\Route;

final class RefBookController
{
    #[Route(
        path: '/api/refbook/health',
        methods: ['GET'],
        name: 'refbook_api_health'
    )]
    public function health(): array
    {
        return [
            'ok' => true,
            'api_version' => 'opus-refbook-internal/v1',
        ];
    }
}
