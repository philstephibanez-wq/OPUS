<?php

declare(strict_types=1);

namespace Opus\Tests\Fixtures\P112Q1;

use ASAP\Routing\Route;

final class DemoRouteController
{
    #[Route(path: '/kb/search', name: 'kb.search', methods: ['GET'], acl: 'kb.read')]
    public function search(): void
    {
    }

    #[Route(path: '/kb/item/{id}', name: 'kb.item', methods: ['GET'], acl: 'kb.read', priority: 10)]
    public function item(): void
    {
    }
}
