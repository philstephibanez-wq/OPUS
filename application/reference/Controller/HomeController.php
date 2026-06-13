<?php
declare(strict_types=1);

namespace OpusRefBook\Reference\Controller;

use ASAP\Http\Request;
use ASAP\Renderer\ViewModel;

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
            return $this->view('pages/api-reference.twig', [
                'title' => $this->content()->t('api.title'),
                'overview' => $this->catalog()->overview(),
            ]);
        }

        if ($page === 'search') {
            $query = (string) ($_GET['q'] ?? '');

            return $this->view('pages/search.twig', [
                'title' => $this->content()->t('search.title'),
                'search' => $this->search()->search($query),
            ]);
        }

        $guide = $this->content()->guideBySlug($page);
        if ($guide !== null) {
            return $this->view('pages/guide.twig', [
                'title' => (string) ($guide['title'] ?? 'Guide'),
                'guide' => $guide,
            ]);
        }

        if (str_starts_with($page, 'domain-')) {
            $domain = $this->catalog()->domainBySlug(substr($page, 7));
            if ($domain !== null) {
                return $this->view('pages/domain.twig', [
                    'title' => $this->content()->t('domain.kicker') . ' ' . $domain['name'],
                    'domain' => $domain,
                ]);
            }
        }

        if (str_starts_with($page, 'symbol-')) {
            $symbol = $this->catalog()->symbolByIndex((int) substr($page, 7));
            if ($symbol !== null) {
                return $this->view('pages/symbol.twig', [
                    'title' => (string) ($symbol['symbol'] ?? $symbol['name'] ?? $this->content()->t('symbol.fallback_name')),
                    'symbol' => $symbol,
                ]);
            }
        }

        if ($page !== '') {
            return $this->view('pages/not-found.twig', [
                'title' => $this->content()->t('not_found.title'),
                'slug' => $page,
            ], 404);
        }

        return $this->view('pages/home.twig', [
            'title' => (string) ($this->content()->module()['title'] ?? 'Opus Reference Book'),
            'overview' => $this->catalog()->overview(),
            'homeCards' => $this->content()->homeCards(),
            'guides' => $this->content()->guides(),
        ]);
    }
}