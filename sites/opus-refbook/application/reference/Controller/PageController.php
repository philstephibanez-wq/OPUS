<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Controller;

use Opus\Http\Request;
use Opus\Renderer\ViewModel;

/**
 * PUBLIC CONTROLLER
 *
 * Role:
 *   Resolve simple RefBook slugs to guide, diagnostic, domain and symbol pages.
 *
 * Contract:
 *   Controller only. Unknown slugs return explicit 404 ViewModel.
 */
final class PageController extends AbstractRefBookController
{
    /**
     * @param array<string,string> $params
     */
    public function show(Request $request, array $params): ViewModel
    {
        $slug = (string) ($params['slug'] ?? '');

        if ($slug === 'search') {
            $query = (string) ($_GET['q'] ?? '');

            return $this->view('pages/search.score', [
                'title' => $this->content()->t('search.title'),
                'search' => $this->search()->search($query),
                'pageSlug' => 'search',
            ]);
        }

        if ($slug === 'asset-diagnostics') {
            return $this->view('pages/asset-diagnostics.score', [
                'title' => $this->content()->t('assets.title'),
                'diagnostics' => $this->catalog()->assetDiagnostics(),
                'pageSlug' => 'asset-diagnostics',
            ]);
        }

        if ($slug === 'legal') {
            return $this->view('pages/legal.score', [
                'title' => $this->content()->t('legal.title'),
                'pageSlug' => 'legal',
            ]);
        }

        if ($slug === 'download-install') {
            $releaseService = new \OpusRefBook\Reference\Service\ReferenceOpusReleaseService(
                $this->paths->appRoot . '/content/refbook/releases',
                \OpusRefBook\Reference\Service\ReferenceOpusRootLocator::fromEnvironment(),
                $this->content()->language()
            );

            return $this->view('pages/download-install.score', [
                'title' => $releaseService->pageTitle(),
                'downloadInstall' => $releaseService->viewModel(),
                'pageSlug' => 'download-install',
            ]);
        }
        $guide = $this->content()->guideBySlug($slug);
        if ($guide !== null) {
            return $this->view('pages/guide.score', [
                'title' => (string) ($guide['title'] ?? 'Guide'),
                'guide' => $guide,
                'pageSlug' => $slug,
            ]);
        }

        if (str_starts_with($slug, 'domain-')) {
            $domain = $this->catalog()->domainBySlug(substr($slug, 7));

            if ($domain !== null) {
                return $this->view('pages/domain.score', [
                    'title' => $this->content()->t('domain.kicker') . ' ' . $domain['name'],
                    'domain' => $domain,
                    'pageSlug' => $slug,
                ]);
            }
        }

        if (str_starts_with($slug, 'symbol-')) {
            $symbol = $this->catalog()->symbolByIndex((int) substr($slug, 7));

            if ($symbol !== null) {
                return $this->view('pages/symbol.score', [
                    'title' => (string) ($symbol['symbol'] ?? $symbol['name'] ?? $this->content()->t('symbol.fallback_name')),
                    'symbol' => $symbol,
                    'pageSlug' => $slug,
                ]);
            }
        }

        return $this->view('pages/not-found.score', [
            'title' => $this->content()->t('not_found.title'),
            'slug' => $slug,
        ], 404);
    }
}
