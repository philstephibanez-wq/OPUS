<?php
declare(strict_types=1);

namespace Opus;

use Opus\Template\ScoreTemplateRenderer;

final class View
{
    private Kernel $kernel;
    private I18n $i18n;

    public function __construct(Kernel $kernel, I18n $i18n)
    {
        $this->kernel = $kernel;
        $this->i18n = $i18n;
    }

    /** @param array<string,mixed> $page */
    public function render(Package $package, string $lang, string $pageId, array $page): string
    {
        $title = (string)($page['title'] ?? $package->name);
        $description = (string)($page['description'] ?? '');
        $theme = (string)($package->meta['theme'] ?? 'blue');
        $assetCss = $this->kernel->assetUrl($package, 'assets/css/site.css');
        $assetJs = $this->kernel->assetUrl($package, 'assets/js/site.js');
        $homeUrl = $this->kernel->packageUrl($package->slug, '', $lang);
        $switcher = $this->renderLanguageSwitcher($package, $lang, $pageId);
        $mainNav = $this->renderMainNav($package, $lang);
        $packageNav = $this->renderPackageNav($package, $lang, $pageId);
        $body = $this->renderBody($package, $lang, $page);
        $year = date('Y');
        $badge = (string)($package->meta['badge'] ?? 'OPUS');
        $footerTagline = $this->footerTagline($lang);

        return $this->renderLayout([
            'lang' => $lang,
            'theme' => $theme,
            'title' => $title,
            'description' => $description,
            'package' => [
                'name' => $package->name,
                'slug' => $package->slug,
            ],
            'assets' => [
                'css' => $assetCss,
                'js' => $assetJs,
            ],
            'urls' => [
                'home' => $homeUrl,
            ],
            'nav' => [
                'main' => $mainNav,
                'package' => $packageNav,
                'switcher' => $switcher,
            ],
            'body' => [
                'html' => $body,
            ],
            'footer' => [
                'year' => $year,
                'tagline' => $footerTagline,
            ],
            'badge' => $badge,
        ]);
    }

    /** @param array<string,mixed> $data */
    private function renderLayout(array $data): string
    {
        require_once __DIR__ . '/Score/TemplateException.php';
        require_once __DIR__ . '/Score/TemplateRendererInterface.php';
        require_once __DIR__ . '/Score/ScoreTemplateRenderer.php';

        $renderer = new ScoreTemplateRenderer(__DIR__ . '/Score/templates/view');
        return $renderer->render('layout.score', $data);
    }

    private function renderLanguageSwitcher(Package $package, string $lang, string $pageId): string
    {
        $links = [];
        foreach ($package->languages as $candidate) {
            $href = $this->kernel->pageUrl($package, $candidate, $pageId);
            $class = $candidate === $lang ? 'active' : '';
            $label = strtoupper($candidate);
            $links[] = "<a class=\"{$class}\" href=\"{$href}\">{$label}</a>";
        }
        return '<nav class="lang-switcher" aria-label="Language switcher">' . implode('', $links) . '</nav>';
    }

    private function renderMainNav(Package $package, string $lang): string
    {
        $items = [
            ['logandplay', $this->i18n->t($package, $lang, 'nav.logandplay')],
            ['demo', $this->i18n->t($package, $lang, 'nav.demo')],
            ['maestro', $this->i18n->t($package, $lang, 'nav.maestro')],
        ];
        $html = [];
        foreach ($items as [$slug, $label]) {
            $href = $this->kernel->packageUrl($slug, '', $lang);
            $html[] = '<a href="' . $href . '">' . $this->esc($label) . '</a>';
        }
        return '<nav class="main-nav" aria-label="Packages">' . implode('', $html) . '</nav>';
    }

    private function renderPackageNav(Package $package, string $lang, string $currentPageId): string
    {
        $routes = $package->routes();
        $content = $package->content();
        $langRoutes = (array)($routes[$lang] ?? []);
        if (!$langRoutes) {
            return '';
        }
        $html = [];
        $seen = [];
        foreach ($langRoutes as $slug => $pageId) {
            if ($slug === '' || isset($seen[$pageId])) {
                continue;
            }
            $seen[$pageId] = true;
            $href = $this->kernel->packageUrl($package->slug, $slug, $lang);
            $page = (array)($content[$lang][$pageId] ?? []);
            $label = (string)($page['nav'] ?? $page['title'] ?? $this->labelFor((string)$pageId));
            $class = $pageId === $currentPageId ? ' class="active"' : '';
            $html[] = '<a' . $class . ' href="' . $href . '">' . $this->esc($label) . '</a>';
        }
        return '<nav class="package-nav" aria-label="Package navigation">' . implode('', $html) . '</nav>';
    }

    /** @param array<string,mixed> $page */
    private function renderBody(Package $package, string $lang, array $page): string
    {
        if (isset($page['html'])) {
            return '<article class="doc-content">' . $this->resolveInlineLinks((string)$page['html'], $package, $lang) . '</article>';
        }

        $title = $this->esc((string)($page['title'] ?? $package->name));
        $kicker = $this->esc((string)($page['kicker'] ?? $package->slug));
        $lead = $this->esc((string)($page['lead'] ?? ''));
        $sections = $this->renderSections((array)($page['sections'] ?? []));
        $cards = $this->renderCards((array)($page['cards'] ?? []), $package, $lang);
        $flow = $this->renderFlow((array)($page['flow'] ?? []));

        return <<<HTML
<section class="hero">
  <p class="kicker">{$kicker}</p>
  <h1>{$title}</h1>
  <p class="lead">{$lead}</p>
</section>
{$cards}
{$sections}
{$flow}
HTML;
    }

    /** @param list<array<string,mixed>> $cards */
    private function renderCards(array $cards, Package $package, string $lang): string
    {
        if (!$cards) return '';
        $html = [];
        foreach ($cards as $card) {
            $title = $this->esc((string)($card['title'] ?? ''));
            $text = $this->esc((string)($card['text'] ?? ''));
            $href = $this->resolveHref((string)($card['href'] ?? ''), $package, $lang);
            $cta = $this->esc((string)($card['cta'] ?? $this->defaultCta($lang)));
            $link = $href !== '' ? '<a class="card-link" href="' . $this->esc($href) . '">' . $cta . '</a>' : '';
            $html[] = "<article class=\"card\"><h3>{$title}</h3><p>{$text}</p>{$link}</article>";
        }
        return '<section class="card-grid">' . implode('', $html) . '</section>';
    }

    /** @param list<array<string,mixed>> $sections */
    private function renderSections(array $sections): string
    {
        if (!$sections) return '';
        $html = [];
        foreach ($sections as $section) {
            $title = $this->esc((string)($section['title'] ?? ''));
            $text = $this->esc((string)($section['text'] ?? ''));
            $items = '';
            foreach ((array)($section['items'] ?? []) as $item) $items .= '<li>' . $this->esc((string)$item) . '</li>';
            $list = $items !== '' ? '<ul>' . $items . '</ul>' : '';
            $html[] = "<section class=\"panel\"><h2>{$title}</h2><p>{$text}</p>{$list}</section>";
        }
        return '<div class="content-stack">' . implode('', $html) . '</div>';
    }

    /** @param list<array<string,string>> $flow */
    private function renderFlow(array $flow): string
    {
        if (!$flow) return '';
        $rows = '';
        foreach ($flow as $step) {
            $rows .= '<tr><td>' . $this->esc($step['state'] ?? '') . '</td><td>' . $this->esc($step['signal'] ?? '') . '</td><td>' . $this->esc($step['action'] ?? '') . '</td><td>' . $this->esc($step['next'] ?? '') . '</td></tr>';
        }
        return '<section class="panel"><h2>FSM trace</h2><div class="table-wrap"><table><thead><tr><th>State</th><th>Signal</th><th>Action</th><th>Next</th></tr></thead><tbody>' . $rows . '</tbody></table></div></section>';
    }

    private function resolveInlineLinks(string $html, Package $package, string $lang): string
    {
        return preg_replace_callback('/href="([^"]*)"/', function (array $m) use ($package, $lang): string {
            $href = (string)$m[1];
            if (preg_match('/^@route\/(.*)$/', $href, $r)) {
                return 'href="' . $this->esc($this->kernel->packageUrl($package->slug, $r[1], $lang)) . '"';
            }
            return 'href="' . $this->esc($this->resolveHref($href, $package, $lang)) . '"';
        }, $html) ?? $html;
    }

    private function resolveHref(string $href, Package $package, string $lang): string
    {
        if ($href === '') return '';
        if (preg_match('/^@api\/([a-z0-9_-]+)\/(.*)$/', $href, $m)) return $this->kernel->apiUrl($m[1], $m[2]);
        if (preg_match('/^@asset\/(.*)$/', $href, $m)) return $this->kernel->assetUrl($package, $m[1]);
        if (preg_match('/^@([a-z0-9_-]+)\/([a-z]{2})(?:\/(.*))?$/', $href, $m)) return $this->kernel->packageUrl($m[1], $m[3] ?? '', $m[2]);
        if (preg_match('/^https?:\/\//', $href)) return $href;
        if ($href[0] === '/') return $href;
        return $this->kernel->packageUrl($package->slug, $href, $lang);
    }


    private function defaultCta(string $lang): string
    {
        return match ($lang) {
            'en' => 'Explore',
            'es' => 'Descubrir',
            default => 'Découvrir',
        };
    }

    private function footerTagline(string $lang): string
    {
        return match ($lang) {
            'en' => 'Official ecosystem · integrated websites · internal links',
            'es' => 'Ecosistema oficial · sitios integrados · enlaces internos',
            default => 'Écosystème officiel · sites intégrés · liens internes',
        };
    }

    private function labelFor(string $pageId): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $pageId));
    }

    private function esc(string $value): string
    {
        return Support::e($value);
    }
}
