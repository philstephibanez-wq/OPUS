<?php
declare(strict_types=1);

namespace Opus\View;

use Opus\Kernel;

use Opus\I18n\I18n;

use Opus\Application\ApplicationDefinition;
use Opus\Foundation\Support;

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
    public function render(ApplicationDefinition $application, string $lang, string $pageId, array $page): string
    {
        $title = (string)($page['title'] ?? $application->name);
        $description = (string)($page['description'] ?? '');
        $theme = (string)($application->meta['theme'] ?? 'blue');
        $assetCss = $this->kernel->assetUrl($application, 'assets/css/site.css');
        $assetJs = $this->kernel->assetUrl($application, 'assets/js/site.js');
        $homeUrl = $this->kernel->applicationUrl($application->slug, '', $lang);
        $switcher = $this->renderLanguageSwitcher($application, $lang, $pageId);
        $mainNav = $this->renderMainNav($application, $lang);
        $applicationNav = $this->renderPackageNav($application, $lang, $pageId);
        $body = $this->renderBody($application, $lang, $page);
        $year = date('Y');
        $badge = (string)($application->meta['badge'] ?? 'OPUS');
        $footerTagline = $this->footerTagline($lang);

        return $this->renderLayout([
            'lang' => $lang,
            'theme' => $theme,
            'title' => $title,
            'description' => $description,
            'package' => [
                'name' => $application->name,
                'slug' => $application->slug,
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
                'package' => $applicationNav,
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
        require_once __DIR__ . '/../Score/TemplateException.php';
        require_once __DIR__ . '/../Score/TemplateRendererInterface.php';
        require_once __DIR__ . '/../Score/ScoreTemplateRenderer.php';

        $renderer = new ScoreTemplateRenderer(__DIR__ . '/../Score/templates/view');
        return $renderer->render('layout.score', $data);
    }

    private function renderLanguageSwitcher(ApplicationDefinition $application, string $lang, string $pageId): string
    {
        $links = [];
        foreach ($application->languages as $candidate) {
            $href = $this->kernel->pageUrl($application, $candidate, $pageId);
            $class = $candidate === $lang ? 'active' : '';
            $label = strtoupper($candidate);
            $links[] = "<a class=\"{$class}\" href=\"{$href}\">{$label}</a>";
        }
        return '<nav class="lang-switcher" aria-label="Language switcher">' . implode('', $links) . '</nav>';
    }

    private function renderMainNav(ApplicationDefinition $application, string $lang): string
    {
        $items = [
            ['logandplay', $this->i18n->t($application, $lang, 'nav.logandplay')],
            ['demo', $this->i18n->t($application, $lang, 'nav.demo')],
            ['maestro', $this->i18n->t($application, $lang, 'nav.maestro')],
        ];
        $html = [];
        foreach ($items as [$slug, $label]) {
            $href = $this->kernel->applicationUrl($slug, '', $lang);
            $html[] = '<a href="' . $href . '">' . $this->esc($label) . '</a>';
        }
        return '<nav class="main-nav" aria-label="Packages">' . implode('', $html) . '</nav>';
    }

    private function renderPackageNav(ApplicationDefinition $application, string $lang, string $currentPageId): string
    {
        $routes = $application->routes();
        $content = $application->content();
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
            $href = $this->kernel->applicationUrl($application->slug, $slug, $lang);
            $page = (array)($content[$lang][$pageId] ?? []);
            $label = (string)($page['nav'] ?? $page['title'] ?? $this->labelFor((string)$pageId));
            $class = $pageId === $currentPageId ? ' class="active"' : '';
            $html[] = '<a' . $class . ' href="' . $href . '">' . $this->esc($label) . '</a>';
        }
        return '<nav class="package-nav" aria-label="Package navigation">' . implode('', $html) . '</nav>';
    }

    /** @param array<string,mixed> $page */
    private function renderBody(ApplicationDefinition $application, string $lang, array $page): string
    {
        if (isset($page['html'])) {
            return '<article class="doc-content">' . $this->resolveInlineLinks((string)$page['html'], $application, $lang) . '</article>';
        }

        $title = $this->esc((string)($page['title'] ?? $application->name));
        $kicker = $this->esc((string)($page['kicker'] ?? $application->slug));
        $lead = $this->esc((string)($page['lead'] ?? ''));
        $sections = $this->renderSections((array)($page['sections'] ?? []));
        $cards = $this->renderCards((array)($page['cards'] ?? []), $application, $lang);
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
    private function renderCards(array $cards, ApplicationDefinition $application, string $lang): string
    {
        if (!$cards) return '';
        $html = [];
        foreach ($cards as $card) {
            $title = $this->esc((string)($card['title'] ?? ''));
            $text = $this->esc((string)($card['text'] ?? ''));
            $href = $this->resolveHref((string)($card['href'] ?? ''), $application, $lang);
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

    private function resolveInlineLinks(string $html, ApplicationDefinition $application, string $lang): string
    {
        return preg_replace_callback('/href="([^"]*)"/', function (array $m) use ($application, $lang): string {
            $href = (string)$m[1];
            if (preg_match('/^@route\/(.*)$/', $href, $r)) {
                return 'href="' . $this->esc($this->kernel->applicationUrl($application->slug, $r[1], $lang)) . '"';
            }
            return 'href="' . $this->esc($this->resolveHref($href, $application, $lang)) . '"';
        }, $html) ?? $html;
    }

    private function resolveHref(string $href, ApplicationDefinition $application, string $lang): string
    {
        if ($href === '') return '';
        if (preg_match('/^@api\/([a-z0-9_-]+)\/(.*)$/', $href, $m)) return $this->kernel->apiUrl($m[1], $m[2]);
        if (preg_match('/^@asset\/(.*)$/', $href, $m)) return $this->kernel->assetUrl($application, $m[1]);
        if (preg_match('/^@([a-z0-9_-]+)\/([a-z]{2})(?:\/(.*))?$/', $href, $m)) return $this->kernel->applicationUrl($m[1], $m[3] ?? '', $m[2]);
        if (preg_match('/^https?:\/\//', $href)) return $href;
        if ($href[0] === '/') return $href;
        return $this->kernel->applicationUrl($application->slug, $href, $lang);
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
