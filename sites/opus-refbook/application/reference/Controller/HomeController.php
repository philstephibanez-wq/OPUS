<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Controller;

use Opus\Http\Request;
use Opus\Renderer\ViewModel;

/**
 * PUBLIC CONTROLLER
 *
 * Role:
 *   Render the RefBook home page and no-htaccess query navigation.
 *
 * Contract:
 *   Controller only. Delegates data preparation to services.
 *   Query navigation exists only because Apache rewrite is intentionally not required.
 */
final class HomeController extends AbstractRefBookController
{
    /**
     * @param array<string,string> $params
     */
    public function index(Request $request, array $params): ViewModel
    {
        $page = trim((string) ($_GET['page'] ?? ''));

        if ($page === 'api-reference') {
            return $this->view('pages/api-reference.score', [
                'title' => $this->content()->t('api.title'),
                'overview' => $this->catalog()->overview(),
                'pageSlug' => 'api-reference',
            ]);
        }

        if ($page === 'asset-diagnostics') {
            return $this->view('pages/asset-diagnostics.score', [
                'title' => $this->content()->t('assets.title'),
                'diagnostics' => $this->catalog()->assetDiagnostics(),
                'pageSlug' => 'asset-diagnostics',
            ]);
        }

        if ($page === 'legal') {
            return $this->view('pages/legal.score', [
                'title' => $this->content()->t('legal.title'),
                'pageSlug' => 'legal',
            ]);
        }

        if ($page === 'download-install') {
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

        if ($page === 'search') {
            $query = (string) ($_GET['q'] ?? '');

            return $this->view('pages/search.score', [
                'title' => $this->content()->t('search.title'),
                'search' => $this->search()->search($query),
                'pageSlug' => 'search',
            ]);
        }

        $guide = $this->content()->guideBySlug($page);
        if ($guide !== null) {
            return $this->view('pages/guide.score', [
                'title' => (string) ($guide['title'] ?? 'Guide'),
                'guide' => $guide,
                'pageSlug' => $page,
            ]);
        }

        if (str_starts_with($page, 'domain-')) {
            $domain = $this->catalog()->domainBySlug(substr($page, 7));
            if ($domain !== null) {
                return $this->view('pages/domain.score', [
                    'title' => $this->content()->t('domain.kicker') . ' ' . $domain['name'],
                    'domain' => $domain,
                    'pageSlug' => $page,
                ]);
            }
        }

        if (str_starts_with($page, 'symbol-')) {
            $symbol = $this->catalog()->symbolByIndex((int) substr($page, 7));
            if ($symbol !== null) {
                return $this->view('pages/symbol.score', [
                    'title' => (string) ($symbol['symbol'] ?? $symbol['name'] ?? $this->content()->t('symbol.fallback_name')),
                    'symbol' => $symbol,
                    'pageSlug' => $page,
                ]);
            }
        }

        if ($page !== '') {
            return $this->view('pages/not-found.score', [
                'title' => $this->content()->t('not_found.title'),
                'slug' => $page,
            ], 404);
        }

        return $this->view('pages/home.score', [
            'title' => (string) ($this->content()->module()['title'] ?? 'Opus Reference Book'),
            'overview' => $this->catalog()->overview(),
            'homeCards' => $this->content()->homeCards(),
            'guides' => $this->content()->guides(),
        ]);
    }
}
