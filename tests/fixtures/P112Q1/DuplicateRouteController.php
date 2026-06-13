<?php

declare(strict_types=1);

namespace Opus\Tests\Fixtures\P112Q1;

use ASAP\Routing\Route;

final class DuplicateRouteController
{
    #[Route(path: '/kb/search', name: 'kb.search.duplicate', methods: ['GET'])]
    public function searchDuplicatePath(): void
    {
    }
}
