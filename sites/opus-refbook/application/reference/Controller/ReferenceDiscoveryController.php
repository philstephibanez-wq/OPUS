<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Controller;

use Opus\Http\Request;
use Opus\Http\Response;

/**
 * PUBLIC CONTROLLER
 *
 * Role:
 *   Expose public discovery documents for crawlers.
 *
 * Contract:
 *   HTTP boundary only. SEO/discovery data is prepared by ReferencePublicSiteService.
 */
final class ReferenceDiscoveryController extends AbstractRefBookController
{
    /**
     * @param array<string,string> $params
     */
    public function robots(Request $request, array $params): Response
    {
        return new Response(
            $this->publicSite()->robotsTxt(),
            200,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }

    /**
     * @param array<string,string> $params
     */
    public function sitemap(Request $request, array $params): Response
    {
        $overview = $this->catalog()->overview();

        return new Response(
            $this->publicSite()->sitemapXml(
                $this->catalog()->allSymbols(),
                $this->content()->guideNavigation(),
                $overview['domains'] ?? []
            ),
            200,
            ['Content-Type' => 'application/xml; charset=utf-8']
        );
    }
}
