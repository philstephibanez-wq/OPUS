<?php

declare(strict_types=1);

namespace OpusRefBook\Reference\Service;

use Opus\Application\ApplicationPaths;
use Opus\Breadcrumb\RouterBreadcrumbBuilder;
use Opus\Http\Request;
use Opus\Routing\Router;
use Opus\Site\SiteResolver;
use RuntimeException;

final class ReferenceBreadcrumbService
{
    public function __construct(
        private readonly ApplicationPaths $paths,
        private readonly ReferenceContentService $content,
        private readonly ReferencePublicSiteService $publicSite
    ) {
    }

    /** @return list<array{label:string,href:string,current:bool}> */
    public function trail(string $title, string $pageSlug, string $theme): array
    {
        $title = trim($title);
        $theme = trim($theme);

        if ($title === '') {
            throw new RuntimeException('OPUS_REFBOOK_BREADCRUMB_TITLE_EMPTY');
        }

        if ($theme === '') {
            throw new RuntimeException('OPUS_REFBOOK_BREADCRUMB_THEME_EMPTY');
        }

        $request = Request::fromGlobals();
        $site = (new SiteResolver($this->paths->sitesRoot))->resolve($request);
        $match = Router::fromXml($site->routesFile)->match($request, $site);

        $base = $this->publicSite->basePath();
        $language = $this->content->language();
        $homeHref = $base . '/?lang=' . rawurlencode($language) . '&theme=' . rawurlencode($theme);
        $currentHref = $homeHref;

        if (trim($pageSlug) !== '') {
            $currentHref .= '&page=' . rawurlencode($pageSlug);
        }

        return (new RouterBreadcrumbBuilder($this->content->t('breadcrumb.home'), $homeHref))
            ->forMatch($match, $title, $currentHref);
    }
}
