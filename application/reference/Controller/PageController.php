<?php
declare(strict_types=1);

namespace ASAPRefBook\Reference\Controller;

use ASAP\Http\Request;
use ASAP\Renderer\ViewModel;

/**
 * PUBLIC CONTROLLER
 *
 * Role:
 *   Resolve simple RefBook slugs to guide, domain and symbol pages.
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

            return $this->view('pages/search.twig', [
                'title' => $this->content()->t('search.title'),
                'search' => $this->search()->search($query),
            ]);
        }

        $guide = $this->content()->guideBySlug($slug);
        if ($guide !== null) {
            return $this->view('pages/guide.twig', [
                'title' => (string) ($guide['title'] ?? 'Guide'),
                'guide' => $guide,
            ]);
        }

        if (str_starts_with($slug, 'domain-')) {
            $domain = $this->catalog()->domainBySlug(substr($slug, 7));

            if ($domain !== null) {
                return $this->view('pages/domain.twig', [
                    'title' => $this->content()->t('domain.kicker') . ' ' . $domain['name'],
                    'domain' => $domain,
                ]);
            }
        }

        if (str_starts_with($slug, 'symbol-')) {
            $symbol = $this->catalog()->symbolByIndex((int) substr($slug, 7));

            if ($symbol !== null) {
                return $this->view('pages/symbol.twig', [
                    'title' => (string) ($symbol['symbol'] ?? $symbol['name'] ?? $this->content()->t('symbol.fallback_name')),
                    'symbol' => $symbol,
                ]);
            }
        }

        return $this->view('pages/not-found.twig', [
            'title' => $this->content()->t('not_found.title'),
            'slug' => $slug,
        ], 404);
    }
}