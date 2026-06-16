<?php

declare(strict_types=1);

namespace Opus\PublicSite;

use Opus\Http\PublicRequest;

final class PublicHomeAction
{
    public function __invoke(PublicRequest $request): PublicPageModel
    {
        return new PublicPageModel('OPUS', 'Public page for ' . $request->site() . '.');
    }
}
