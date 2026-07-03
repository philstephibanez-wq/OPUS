<?php
declare(strict_types=1);

namespace Opus\Scaffold;

/**
 * Scaffold plan for a full OPUS site/application.
 *
 * Eternal contract:
 * - OPUS keeps the ASAP application tree model;
 * - every site has application/default as the inherited common layer;
 * - every controller has its own application/<controller> tree;
 * - CSS, JS and theme assets are resolved in deterministic ASAP order;
 * - public exposure is under www/asset, not public/assets;
 * - no silent fallback is allowed for mandatory structural resources.
 */
final class SiteScaffoldPlan implements ScaffoldPlanInterface, SiteScaffoldPlanInterface
{
    private const CONTRACT = 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL';
    private const STARTER_CONTRACT = 'OPUS_ASAP_INHERITANCE_STARTER_V1';

    private function __construct(private readonly string $siteId)
    {
    }

    public static function forSite(string $siteId): self
    {
        return new self($siteId);
    }

    public function rootRelativePath(): string
    {
        return 'sites/' . $this->siteId;
    }

    /**
     * @return list<ScaffoldEntry>
     */
    public function entries(): array
    {
        $site = $this->siteId;

        $directories = [
            "sites/{$site}/config",
            "sites/{$site}/application/default/acl",
            "sites/{$site}/application/default/helpers",
            "sites/{$site}/application/default/css",
            "sites/{$site}/application/default/javascript",
            "sites/{$site}/application/default/local/fr",
            "sites/{$site}/application/default/local/en",
            "sites/{$site}/application/default/local/es",
            "sites/{$site}/application/default/models",
            "sites/{$site}/application/default/templates/components",
            "sites/{$site}/application/default/views",
            "sites/{$site}/www/asset/css",
            "sites/{$site}/www/asset/js",
            "sites/{$site}/www/asset/themes/starter/css",
            "sites/{$site}/www/asset/themes/starter/js",
            "sites/{$site}/www/asset/themes/starter/img",
        ];

        foreach ($this->starterPages() as $page) {
            $controller = $page['id'];
            foreach ($this->controllerSubdirectories($controller) as $directory) {
                $directories[] = "sites/{$site}/application/{$directory}";
            }
        }

        $entries = array_map(
            static fn (string $directory): ScaffoldEntry => ScaffoldEntry::directory($directory),
            array_values(array_unique($directories))
        );

        $entries[] = ScaffoldEntry::file("sites/{$site}/README.md", $this->readmeContent());
        $entries[] = ScaffoldEntry::file("sites/{$site}/START_HERE.md", $this->startHereContent());
        $entries[] = ScaffoldEntry::file("sites/{$site}/opus-site.json", $this->json([
            'site_id' => $site,
            'type' => 'opus-site',
            'contract' => self::CONTRACT,
            'starter_contract' => self::STARTER_CONTRACT,
            'asap_reference' => 'https://asap.logandplay.org/ASAP_PHP_DEMO/fr/articles',
            'created_by' => 'composer opus:create-site',
        ]));

        $entries[] = ScaffoldEntry::file("sites/{$site}/config/site.json", $this->json([
            'site_id' => $site,
            'site_name' => 'OPUS Demo ' . $site,
            'contract' => self::CONTRACT,
            'starter_contract' => self::STARTER_CONTRACT,
            'default_locale' => 'fr',
            'locales' => ['fr', 'en', 'es'],
            'theme' => 'starter',
            'application_root' => 'application',
            'default_root' => 'application/default',
            'controller_root_pattern' => 'application/<controller>',
            'public_root' => 'www',
            'asset_root' => 'www/asset',
            'theme_root_pattern' => 'www/asset/themes/<theme>',
            'css_inheritance' => [
                'application/default/css',
                'www/asset/themes/<theme>/css',
                'application/<controller>/css',
            ],
            'js_inheritance' => [
                'application/default/javascript',
                'www/asset/themes/<theme>/js',
                'application/<controller>/javascript',
            ],
            'home_route' => 'home.index',
        ]));

        $entries[] = ScaffoldEntry::file("sites/{$site}/config/routes.json", $this->json($this->routesConfig()));
        $entries[] = ScaffoldEntry::file("sites/{$site}/config/menu.json", $this->json($this->menuConfig()));
        $entries[] = ScaffoldEntry::file("sites/{$site}/config/fsm.json", $this->json($this->fsmConfig()));
        $entries[] = ScaffoldEntry::file("sites/{$site}/config/rubrics.json", $this->json($this->rubricsConfig()));

        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/templates/layout.score", $this->layoutScore());
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/templates/components/header.score", $this->headerScore());
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/templates/components/footer.score", $this->footerScore());
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/templates/components/menu-item.score", $this->menuItemScore());
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/templates/components/language-selector.score", $this->languageSelectorScore());
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/templates/components/rubric-card.score", $this->rubricCardScore());
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/templates/components/powered-by-opus.score", $this->poweredByScore());

        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/css/default.css", $this->defaultCss());
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/javascript/default.js", $this->defaultJs());
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/local/fr/i18n.json", $this->json($this->defaultI18n('fr')));
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/local/en/i18n.json", $this->json($this->defaultI18n('en')));
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/local/es/i18n.json", $this->json($this->defaultI18n('es')));

        foreach ($this->starterPages() as $page) {
            $controller = $page['id'];
            $entries[] = ScaffoldEntry::file("sites/{$site}/application/{$controller}/templates/index.score", $controller === 'home' ? $this->homePageScore() : $this->rubricPageScore());
            $entries[] = ScaffoldEntry::file("sites/{$site}/application/{$controller}/css/{$controller}.css", $this->controllerCss($controller));
            $entries[] = ScaffoldEntry::file("sites/{$site}/application/{$controller}/javascript/{$controller}.js", $this->controllerJs($controller));
            $entries[] = ScaffoldEntry::file("sites/{$site}/application/{$controller}/local/fr/i18n.json", $this->json($this->controllerI18n('fr', $page)));
            $entries[] = ScaffoldEntry::file("sites/{$site}/application/{$controller}/local/en/i18n.json", $this->json($this->controllerI18n('en', $page)));
            $entries[] = ScaffoldEntry::file("sites/{$site}/application/{$controller}/local/es/i18n.json", $this->json($this->controllerI18n('es', $page)));
            $entries[] = ScaffoldEntry::file("sites/{$site}/application/{$controller}/views/README.md", "# {$controller} views\n\nOPUS controller view resources.\n");
        }

        $entries[] = ScaffoldEntry::file("sites/{$site}/www/asset/themes/starter/css/theme.css", $this->themeCss());
        $entries[] = ScaffoldEntry::file("sites/{$site}/www/asset/themes/starter/js/theme.js", $this->themeJs());
        $entries[] = ScaffoldEntry::file("sites/{$site}/www/index.php", $this->frontControllerContent());

        return $entries;
    }

    /**
     * @return list<array{id:string,path:string,route:string,role:string,label:string,order:int}>
     */
    private function starterPages(): array
    {
        return [
            ['id' => 'home', 'path' => '/', 'route' => 'home.index', 'role' => 'demo-home', 'label' => 'menu.home', 'order' => 10],
            ['id' => 'architecture', 'path' => '/architecture', 'route' => 'architecture.index', 'role' => 'demo-architecture', 'label' => 'menu.architecture', 'order' => 20],
            ['id' => 'router', 'path' => '/router', 'route' => 'router.index', 'role' => 'demo-router', 'label' => 'menu.router', 'order' => 30],
            ['id' => 'modules', 'path' => '/modules', 'route' => 'modules.index', 'role' => 'demo-modules', 'label' => 'menu.modules', 'order' => 40],
            ['id' => 'controllers', 'path' => '/controllers', 'route' => 'controllers.index', 'role' => 'demo-controllers', 'label' => 'menu.controllers', 'order' => 50],
            ['id' => 'views', 'path' => '/views', 'route' => 'views.index', 'role' => 'demo-views', 'label' => 'menu.views', 'order' => 60],
            ['id' => 'models', 'path' => '/models', 'route' => 'models.index', 'role' => 'demo-models', 'label' => 'menu.models', 'order' => 70],
            ['id' => 'i18n', 'path' => '/i18n', 'route' => 'i18n.index', 'role' => 'demo-i18n', 'label' => 'menu.i18n', 'order' => 80],
        ];
    }

    /**
     * @return list<string>
     */
    private function controllerSubdirectories(string $controller): array
    {
        return [
            "{$controller}/acl",
            "{$controller}/helpers",
            "{$controller}/css",
            "{$controller}/javascript",
            "{$controller}/local/fr",
            "{$controller}/local/en",
            "{$controller}/local/es",
            "{$controller}/models",
            "{$controller}/templates",
            "{$controller}/views",
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function routesConfig(): array
    {
        return [
            'contract' => 'OPUS_ROUTE_REGISTRY_V1',
            'routes' => array_map(static fn (array $page): array => [
                'id' => $page['route'],
                'path' => $page['path'],
                'page' => $page['id'],
                'controller' => $page['id'],
                'action' => 'index',
                'template' => 'application/' . $page['id'] . '/templates/index.score',
                'label' => $page['label'],
                'acl' => 'public',
                'fsm_state' => strtoupper(str_replace('-', '_', $page['id'])),
                'show_in_menu' => true,
                'show_on_home' => $page['id'] !== 'home',
                'order' => $page['order'],
            ], $this->starterPages()),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function menuConfig(): array
    {
        return [
            'contract' => 'OPUS_MENU_ROUTE_PROJECTION_V1',
            'source' => 'config/routes.json',
            'items' => array_map(static fn (array $page): array => [
                'route' => $page['route'],
                'controller' => $page['id'],
                'label' => $page['label'],
                'order' => $page['order'],
            ], $this->starterPages()),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function fsmConfig(): array
    {
        return [
            'contract' => 'OPUS_FSM_REGISTRY_V1',
            'initial_state' => 'HOME',
            'states' => array_map(static fn (array $page): array => [
                'id' => strtoupper(str_replace('-', '_', $page['id'])),
                'controller' => $page['id'],
                'route' => $page['route'],
                'role' => $page['role'],
            ], $this->starterPages()),
            'transitions' => [],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function rubricsConfig(): array
    {
        return [
            'contract' => 'OPUS_HOME_DEMO_CARD_ROUTE_PROJECTION_V1',
            'source' => 'config/routes.json',
            'rubrics' => array_values(array_map(static fn (array $page): array => [
                'route' => $page['route'],
                'controller' => $page['id'],
                'order' => $page['order'],
            ], array_filter($this->starterPages(), static fn (array $page): bool => $page['id'] !== 'home'))),
        ];
    }

    private function readmeContent(): string
    {
        return "# {$this->siteId}\n\nOPUS site generated by `composer opus:create-site`.\n\nThis site follows the eternal ASAP/OPUS tree contract: `application/default`, `application/<controller>`, `config`, `www/asset` and deterministic CSS/JS/theme inheritance.\n";
    }

    private function startHereContent(): string
    {
        return <<<'MD'
# Start here

This OPUS site follows the eternal ASAP/OPUS contract.

## Tree

```text
application/default
application/<controller>
config
www/asset
www/asset/themes/<theme>
```

## Inheritance order

```text
CSS = application/default/css + www/asset/themes/<theme>/css + application/<controller>/css
JS  = application/default/javascript + www/asset/themes/<theme>/js + application/<controller>/javascript
```

## Rules

- No local mini framework.
- No silent fallback.
- No `public` root for generated sites.
- `www` is the public root.
- `default` contains shared resources.
- Each controller owns its resources.
MD;
    }

    private function layoutScore(): string
    {
        return <<<'SCORE'
<!doctype html>
<html lang="{{ lang }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ page.title }}</title>
  {{{ assets.css }}}
</head>
<body class="opus-asap-site">
  {{{ common.header }}}
  <main class="opus-shell" id="main-content">
    {{{ content }}}
  </main>
  {{{ common.footer }}}
  {{{ assets.js }}}
</body>
</html>
SCORE;
    }

    private function headerScore(): string
    {
        return <<<'SCORE'
<header class="opus-header">
  <div class="opus-header__inner">
    <div>
      <p class="opus-kicker">OPUS / ASAP CONTRACT</p>
      <h1>{{ site.name }}</h1>
    </div>
    {{{ common.language_selector }}}
  </div>
  <nav class="opus-nav" aria-label="Navigation principale">
    {{{ common.menu }}}
  </nav>
</header>
SCORE;
    }

    private function footerScore(): string
    {
        return <<<'SCORE'
<footer class="opus-footer">
  <div class="opus-footer__inner">
    <span>{{ site.framework }}</span>
    <span>{{ site.contract }}</span>
    {{{ common.powered_by }}}
  </div>
</footer>
SCORE;
    }

    private function menuItemScore(): string
    {
        return <<<'SCORE'
<a class="opus-nav__link {{ menu_item.active_class }}" href="{{ menu_item.path }}">{{ menu_item.label }}</a>
SCORE;
    }

    private function languageSelectorScore(): string
    {
        return <<<'SCORE'
<form class="opus-lang" method="get">
  <label class="opus-lang__label" for="opus-lang-select">{{ i18n.language }}</label>
  <select id="opus-lang-select" name="lang" onchange="this.form.submit()">
    {{{ common.language_options }}}
  </select>
</form>
SCORE;
    }

    private function rubricCardScore(): string
    {
        return <<<'SCORE'
<a class="opus-card" href="{{ rubric.path }}">
  <span>{{ rubric.kicker }}</span>
  <strong>{{ rubric.title }}</strong>
  <p>{{ rubric.description }}</p>
</a>
SCORE;
    }

    private function poweredByScore(): string
    {
        return <<<'SCORE'
<span class="opus-powered">OPUS = ASAP dans sa conception + évolutions</span>
SCORE;
    }

    private function homePageScore(): string
    {
        return <<<'SCORE'
<section class="opus-hero">
  <p class="opus-kicker">{{ page.kicker }}</p>
  <h2>{{ page.title }}</h2>
  <p>{{ page.subtitle }}</p>
</section>
<section class="opus-grid">
  {{{ home.rubric_cards }}}
</section>
SCORE;
    }

    private function rubricPageScore(): string
    {
        return <<<'SCORE'
<article class="opus-page">
  <p class="opus-kicker">{{ page.kicker }}</p>
  <h2>{{ page.title }}</h2>
  <p class="opus-lead">{{ page.subtitle }}</p>
  <section class="opus-panel">
    <h3>{{ page.section_title }}</h3>
    <p>{{ page.section_intro }}</p>
  </section>
</article>
SCORE;
    }

    /**
     * @return array<string,string>
     */
    private function defaultI18n(string $locale): array
    {
        $translations = [
            'fr' => [
                'language' => 'Langue',
                'menu.home' => 'Accueil',
                'menu.architecture' => 'Architecture',
                'menu.router' => 'Router',
                'menu.modules' => 'Modules',
                'menu.controllers' => 'Controllers',
                'menu.views' => 'Views',
                'menu.models' => 'Models/DB',
                'menu.i18n' => 'I18N',
            ],
            'en' => [
                'language' => 'Language',
                'menu.home' => 'Home',
                'menu.architecture' => 'Architecture',
                'menu.router' => 'Router',
                'menu.modules' => 'Modules',
                'menu.controllers' => 'Controllers',
                'menu.views' => 'Views',
                'menu.models' => 'Models/DB',
                'menu.i18n' => 'I18N',
            ],
            'es' => [
                'language' => 'Idioma',
                'menu.home' => 'Inicio',
                'menu.architecture' => 'Arquitectura',
                'menu.router' => 'Router',
                'menu.modules' => 'Módulos',
                'menu.controllers' => 'Controllers',
                'menu.views' => 'Views',
                'menu.models' => 'Models/DB',
                'menu.i18n' => 'I18N',
            ],
        ];

        return $translations[$locale] ?? $translations['fr'];
    }

    /**
     * @param array{id:string,path:string,route:string,role:string,label:string,order:int} $page
     * @return array<string,string>
     */
    private function controllerI18n(string $locale, array $page): array
    {
        $id = $page['id'];
        $titles = [
            'home' => ['fr' => 'Framework OPUS', 'en' => 'OPUS Framework', 'es' => 'Framework OPUS'],
            'architecture' => ['fr' => 'Architecture OPUS', 'en' => 'OPUS Architecture', 'es' => 'Arquitectura OPUS'],
            'router' => ['fr' => 'Router déclaratif', 'en' => 'Declarative router', 'es' => 'Router declarativo'],
            'modules' => ['fr' => 'Modules autonomes', 'en' => 'Autonomous modules', 'es' => 'Módulos autónomos'],
            'controllers' => ['fr' => 'Controllers standardisés', 'en' => 'Standardized controllers', 'es' => 'Controllers estandarizados'],
            'views' => ['fr' => 'Views et templates', 'en' => 'Views and templates', 'es' => 'Views y templates'],
            'models' => ['fr' => 'Models et DB', 'en' => 'Models and DB', 'es' => 'Models y DB'],
            'i18n' => ['fr' => 'Internationalisation', 'en' => 'Internationalization', 'es' => 'Internacionalización'],
        ];
        $title = $titles[$id][$locale] ?? $titles[$id]['fr'] ?? ucfirst($id);

        $subtitles = [
            'fr' => 'Démonstration simple, lisible et contractuelle inspirée d’ASAP.',
            'en' => 'Simple, readable and contractual demonstration inspired by ASAP.',
            'es' => 'Demostración simple, legible y contractual inspirada en ASAP.',
        ];

        return [
            'page.kicker' => strtoupper($id),
            'page.title' => $title,
            'page.subtitle' => $subtitles[$locale] ?? $subtitles['fr'],
            'page.section_title' => $title,
            'page.section_intro' => 'application/default + application/' . $id . ' + www/asset/themes/starter',
        ];
    }

    private function defaultCss(): string
    {
        return <<<'CSS'
:root {
  color-scheme: light;
  --opus-blue: #274f7c;
  --opus-blue-2: #3278b4;
  --opus-bg: #eef3f8;
  --opus-text: #142033;
  --opus-card: #ffffff;
  --opus-border: #d7e0eb;
}
* { box-sizing: border-box; }
body.opus-asap-site {
  margin: 0;
  background: linear-gradient(135deg, #182433, #edf3fa 34%, #edf3fa);
  color: var(--opus-text);
  font: 16px/1.5 system-ui, -apple-system, Segoe UI, Arial, sans-serif;
}
.opus-shell, .opus-header__inner, .opus-nav, .opus-footer__inner {
  width: min(1180px, calc(100% - 32px));
  margin: 0 auto;
}
.opus-header {
  margin: 42px auto 0;
  width: min(1180px, calc(100% - 32px));
  border: 1px solid rgba(255,255,255,.8);
  border-radius: 24px 24px 0 0;
  overflow: hidden;
  background: #253a57;
  color: #fff;
}
.opus-header__inner {
  min-height: 140px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.opus-header h1 { margin: 0; font-size: clamp(2rem, 5vw, 4rem); }
.opus-kicker {
  margin: 0 0 10px;
  text-transform: uppercase;
  letter-spacing: .16em;
  font-weight: 800;
  color: #6aa6e8;
}
.opus-nav {
  display: flex;
  gap: 4px;
  flex-wrap: wrap;
  background: var(--opus-blue-2);
}
.opus-nav__link {
  color: #fff;
  text-decoration: none;
  padding: 18px 20px;
  font-weight: 800;
}
.opus-nav__link--active, .opus-nav__link:hover { background: rgba(0,0,0,.18); }
.opus-shell {
  min-height: 520px;
  background: #f7f9fc;
  padding: 34px 34px 80px;
}
.opus-hero, .opus-page, .opus-panel, .opus-card {
  background: var(--opus-card);
  border: 1px solid var(--opus-border);
  border-radius: 18px;
  padding: 24px;
}
.opus-hero { margin-bottom: 22px; }
.opus-hero h2, .opus-page h2 { margin: 0 0 12px; font-size: clamp(2rem, 4vw, 3rem); }
.opus-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 18px;
}
.opus-card {
  display: block;
  color: inherit;
  text-decoration: none;
  transition: transform .16s ease, border-color .16s ease;
}
.opus-card:hover { transform: translateY(-2px); border-color: #6aa6e8; }
.opus-card span { color: #3676bb; font-weight: 900; font-size: .8rem; text-transform: uppercase; }
.opus-card strong { display: block; margin: 8px 0; font-size: 1.2rem; color: #0b62d6; }
.opus-lead { font-size: 1.15rem; color: #3f4b5f; }
.opus-lang { display: inline-flex; gap: 8px; align-items: center; }
.opus-lang select { border: 0; border-radius: 999px; padding: 8px 12px; font-weight: 800; }
.opus-lang__label { position: absolute; left: -9999px; }
.opus-footer {
  width: min(1180px, calc(100% - 32px));
  margin: 0 auto 40px;
  background: #1b2b43;
  color: #dce9fa;
  border-radius: 0 0 24px 24px;
}
.opus-footer__inner {
  display: flex;
  gap: 18px;
  flex-wrap: wrap;
  justify-content: space-between;
  padding: 18px 0;
  font-size: .92rem;
}
@media (max-width: 720px) {
  .opus-header { margin-top: 16px; }
  .opus-header__inner { display: block; padding: 24px 0; }
  .opus-shell { padding: 22px; }
}
CSS;
    }

    private function defaultJs(): string
    {
        return <<<'JS'
document.documentElement.dataset.opusDefaultLayer = 'loaded';
JS;
    }

    private function themeCss(): string
    {
        return <<<'CSS'
body.opus-asap-site {
  --opus-blue: #24466d;
  --opus-blue-2: #3379b4;
}
CSS;
    }

    private function themeJs(): string
    {
        return <<<'JS'
document.documentElement.dataset.opusThemeLayer = 'starter';
JS;
    }

    private function controllerCss(string $controller): string
    {
        return ".opus-page[data-controller=\"{$controller}\"], .opus-card[data-controller=\"{$controller}\"] { }\n";
    }

    private function controllerJs(string $controller): string
    {
        return "document.documentElement.dataset.opusControllerLayer = '" . addslashes($controller) . "';\n";
    }

    private function frontControllerContent(): string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Opus\Profiler\Profiler;
use Opus\Template\ScoreTemplateRenderer;

$siteRoot = dirname(__DIR__);
$wwwRoot = __DIR__;
$renderer = new ScoreTemplateRenderer($siteRoot);

function opus_fail(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    exit;
}

/**
 * @return array<string,mixed>
 */
function opus_read_json(string $path): array
{
    if (!is_file($path)) {
        opus_fail(500, 'OPUS_REQUIRED_FILE_MISSING: ' . $path);
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        opus_fail(500, 'OPUS_JSON_INVALID: ' . $path);
    }

    return $decoded;
}

function opus_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @return list<string>
 */
function opus_sorted_files(string $directory, string $extension): array
{
    if (!is_dir($directory)) {
        opus_fail(500, 'OPUS_ASSET_DIRECTORY_MISSING: ' . $directory);
    }

    $files = glob($directory . '/*.' . $extension) ?: [];
    sort($files, SORT_STRING);

    return array_values(array_filter($files, 'is_file'));
}

function opus_publish_application_asset(string $source, string $wwwRoot, string $targetRelative): string
{
    if (!is_file($source)) {
        opus_fail(500, 'OPUS_ASSET_SOURCE_MISSING: ' . $source);
    }

    $target = $wwwRoot . '/' . ltrim($targetRelative, '/');
    $targetDirectory = dirname($target);

    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
        opus_fail(500, 'OPUS_ASSET_PUBLICATION_DIRECTORY_FAILED: ' . $targetDirectory);
    }

    if (!is_file($target) || filemtime($source) > filemtime($target)) {
        if (!copy($source, $target)) {
            opus_fail(500, 'OPUS_ASSET_PUBLICATION_FAILED: ' . $source);
        }
    }

    return '/' . str_replace('\\', '/', ltrim($targetRelative, '/')) . '?v=' . (string) filemtime($source);
}

/**
 * @return list<string>
 */
function opus_collect_css(string $siteRoot, string $wwwRoot, string $theme, string $controller): array
{
    $urls = [];

    foreach (opus_sorted_files($siteRoot . '/application/default/css', 'css') as $file) {
        $urls[] = opus_publish_application_asset($file, $wwwRoot, 'asset/css/default-' . basename($file));
    }

    if ($theme === '') {
        opus_fail(500, 'OPUS_THEME_NOT_DECLARED');
    }

    $themeCssDirectory = $wwwRoot . '/asset/themes/' . $theme . '/css';
    foreach (opus_sorted_files($themeCssDirectory, 'css') as $file) {
        $urls[] = '/asset/themes/' . rawurlencode($theme) . '/css/' . rawurlencode(basename($file)) . '?v=' . (string) filemtime($file);
    }

    $controllerRoot = $siteRoot . '/application/' . $controller;
    if (!is_dir($controllerRoot)) {
        opus_fail(500, 'OPUS_CONTROLLER_DIRECTORY_MISSING: ' . $controller);
    }

    foreach (opus_sorted_files($controllerRoot . '/css', 'css') as $file) {
        $urls[] = opus_publish_application_asset($file, $wwwRoot, 'asset/css/' . $controller . '-' . basename($file));
    }

    return $urls;
}

/**
 * @return list<string>
 */
function opus_collect_js(string $siteRoot, string $wwwRoot, string $theme, string $controller): array
{
    $urls = [];

    foreach (opus_sorted_files($siteRoot . '/application/default/javascript', 'js') as $file) {
        $urls[] = opus_publish_application_asset($file, $wwwRoot, 'asset/js/default-' . basename($file));
    }

    if ($theme === '') {
        opus_fail(500, 'OPUS_THEME_NOT_DECLARED');
    }

    $themeJsDirectory = $wwwRoot . '/asset/themes/' . $theme . '/js';
    foreach (opus_sorted_files($themeJsDirectory, 'js') as $file) {
        $urls[] = '/asset/themes/' . rawurlencode($theme) . '/js/' . rawurlencode(basename($file)) . '?v=' . (string) filemtime($file);
    }

    $controllerRoot = $siteRoot . '/application/' . $controller;
    if (!is_dir($controllerRoot)) {
        opus_fail(500, 'OPUS_CONTROLLER_DIRECTORY_MISSING: ' . $controller);
    }

    foreach (opus_sorted_files($controllerRoot . '/javascript', 'js') as $file) {
        $urls[] = opus_publish_application_asset($file, $wwwRoot, 'asset/js/' . $controller . '-' . basename($file));
    }

    return $urls;
}

function opus_css_tags(array $urls): string
{
    $html = '';
    foreach ($urls as $url) {
        $html .= '<link rel="stylesheet" href="' . opus_html((string) $url) . '">' . "\n";
    }

    return $html;
}

function opus_js_tags(array $urls): string
{
    $html = '';
    foreach ($urls as $url) {
        $html .= '<script defer src="' . opus_html((string) $url) . '"></script>' . "\n";
    }

    return $html;
}

/**
 * @return array<string,string>
 */
function opus_i18n_load(string $siteRoot, string $controller, string $locale): array
{
    $defaultPath = $siteRoot . '/application/default/local/' . $locale . '/i18n.json';
    $controllerPath = $siteRoot . '/application/' . $controller . '/local/' . $locale . '/i18n.json';

    $default = opus_read_json($defaultPath);
    $controllerLocal = opus_read_json($controllerPath);

    return array_replace(
        array_map('strval', $default),
        array_map('strval', $controllerLocal)
    );
}

function opus_i18n(array $i18n, string $key): string
{
    return is_scalar($i18n[$key] ?? null) ? (string) $i18n[$key] : $key;
}

function opus_route_url(string $path, string $lang): string
{
    return $path . (str_contains($path, '?') ? '&' : '?') . 'lang=' . rawurlencode($lang);
}

function opus_locale_label(string $locale): string
{
    return ['fr' => 'FR', 'en' => 'EN', 'es' => 'ES'][$locale] ?? strtoupper($locale);
}

function opus_starter_profiler_requested(): bool
{
    return (($_GET['profiler'] ?? '') === '1') || (getenv('OPUS_PROFILER') === '1');
}

function opus_starter_profiler_start(string $siteRoot, string $path): ?Profiler
{
    if (!opus_starter_profiler_requested()) {
        return null;
    }

    $profiler = new Profiler($siteRoot . '/var/profiler');
    $profiler->start();
    $profiler->event('request', 'request.received', ['path' => $path]);

    return $profiler;
}

function opus_starter_profiler_stop(?Profiler $profiler, array $summary = []): void
{
    if ($profiler === null) {
        return;
    }

    $profiler->stop($summary);
}

$profiler = null;

try {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $profiler = opus_starter_profiler_start($siteRoot, $path);

    $siteConfig = opus_read_json($siteRoot . '/config/site.json');
    $routesConfig = opus_read_json($siteRoot . '/config/routes.json');

    $route = null;
    foreach (($routesConfig['routes'] ?? []) as $candidate) {
        if (is_array($candidate) && ($candidate['path'] ?? null) === $path) {
            $route = $candidate;
            break;
        }
    }

    if (!is_array($route)) {
        opus_starter_profiler_stop($profiler, ['status' => 404, 'path' => $path]);
        opus_fail(404, 'OPUS_ROUTE_NOT_FOUND: ' . $path);
    }

    $locales = $siteConfig['locales'] ?? [];
    if (!is_array($locales)) {
        opus_fail(500, 'OPUS_LOCALES_CONTRACT_INVALID');
    }

    $locales = array_values(array_filter($locales, 'is_scalar'));
    $defaultLocale = (string) ($siteConfig['default_locale'] ?? 'fr');
    $queryLocale = isset($_GET['lang']) ? strtolower((string) $_GET['lang']) : '';
    $lang = $queryLocale !== '' ? $queryLocale : $defaultLocale;

    if (!in_array($lang, $locales, true)) {
        opus_fail(400, 'OPUS_LOCALE_UNAVAILABLE: ' . $lang);
    }

    $controller = (string) ($route['controller'] ?? '');
    if ($controller === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $controller)) {
        opus_fail(500, 'OPUS_ROUTE_CONTROLLER_INVALID');
    }

    $theme = (string) ($siteConfig['theme'] ?? '');
    $i18n = opus_i18n_load($siteRoot, $controller, $lang);

    $menuHtml = '';
    foreach (($routesConfig['routes'] ?? []) as $menuRoute) {
        if (!is_array($menuRoute) || ($menuRoute['show_in_menu'] ?? false) !== true) {
            continue;
        }

        $menuData = [
            'menu_item' => [
                'path' => opus_route_url((string) ($menuRoute['path'] ?? '#'), $lang),
                'label' => opus_i18n($i18n, (string) ($menuRoute['label'] ?? '')),
                'active_class' => (($menuRoute['id'] ?? '') === ($route['id'] ?? '')) ? 'opus-nav__link--active' : '',
            ],
        ];
        $menuHtml .= $renderer->render('application/default/templates/components/menu-item.score', $menuData);
    }

    $languageOptions = '';
    foreach ($locales as $locale) {
        $localeValue = (string) $locale;
        $selected = $localeValue === $lang ? ' selected' : '';
        $languageOptions .= '<option value="' . opus_html($localeValue) . '"' . $selected . '>' . opus_html(opus_locale_label($localeValue)) . '</option>';
    }

    $routeUrls = [];
    foreach (($routesConfig['routes'] ?? []) as $configuredRoute) {
        if (is_array($configuredRoute)) {
            $routeUrls[(string) ($configuredRoute['controller'] ?? '')] = opus_route_url((string) ($configuredRoute['path'] ?? '/'), $lang);
        }
    }

    $pageData = [
        'lang' => $lang,
        'assets' => [
            'css' => opus_css_tags(opus_collect_css($siteRoot, $wwwRoot, $theme, $controller)),
            'js' => opus_js_tags(opus_collect_js($siteRoot, $wwwRoot, $theme, $controller)),
        ],
        'site' => [
            'id' => (string) ($siteConfig['site_id'] ?? ''),
            'name' => (string) ($siteConfig['site_name'] ?? ''),
            'framework' => 'OPUS',
            'contract' => (string) ($siteConfig['contract'] ?? ''),
        ],
        'page' => [
            'kicker' => opus_i18n($i18n, 'page.kicker'),
            'title' => opus_i18n($i18n, 'page.title'),
            'subtitle' => opus_i18n($i18n, 'page.subtitle'),
            'section_title' => opus_i18n($i18n, 'page.section_title'),
            'section_intro' => opus_i18n($i18n, 'page.section_intro'),
        ],
        'i18n' => $i18n,
        'routes' => $routeUrls,
        'common' => [
            'menu' => $menuHtml,
            'language_options' => $languageOptions,
        ],
        'home' => [],
    ];

    $pageData['common']['language_selector'] = $renderer->render('application/default/templates/components/language-selector.score', $pageData);

    $rubricCards = '';
    foreach (($routesConfig['routes'] ?? []) as $rubricRoute) {
        if (!is_array($rubricRoute) || ($rubricRoute['show_on_home'] ?? false) !== true) {
            continue;
        }

        $rubricController = (string) ($rubricRoute['controller'] ?? '');
        $rubricI18n = opus_i18n_load($siteRoot, $rubricController, $lang);
        $rubricData = $pageData;
        $rubricData['rubric'] = [
            'path' => opus_route_url((string) ($rubricRoute['path'] ?? '#'), $lang),
            'kicker' => opus_i18n($rubricI18n, 'page.kicker'),
            'title' => opus_i18n($rubricI18n, 'page.title'),
            'description' => opus_i18n($rubricI18n, 'page.subtitle'),
        ];
        $rubricCards .= $renderer->render('application/default/templates/components/rubric-card.score', $rubricData);
    }

    $pageData['home']['rubric_cards'] = $rubricCards;
    $pageData['common']['powered_by'] = $renderer->render('application/default/templates/components/powered-by-opus.score', $pageData);
    $pageData['content'] = $renderer->render((string) ($route['template'] ?? ''), $pageData);
    $pageData['common']['header'] = $renderer->render('application/default/templates/components/header.score', $pageData);
    $pageData['common']['footer'] = $renderer->render('application/default/templates/components/footer.score', $pageData);

    $layoutHtml = $renderer->render('application/default/templates/layout.score', $pageData);
    opus_starter_profiler_stop($profiler, ['status' => 200, 'path' => $path, 'route_id' => (string) ($route['id'] ?? ''), 'locale' => $lang]);

    header('Content-Type: text/html; charset=UTF-8');
    echo $layoutHtml;
} catch (Throwable $exception) {
    opus_starter_profiler_stop($profiler, ['status' => 500, 'error' => 'OPUS_RENDER_FAILED']);
    opus_fail(500, 'OPUS_RENDER_FAILED: ' . $exception->getMessage());
}
PHP;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function json(array $data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}
