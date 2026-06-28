<?php
declare(strict_types=1);

namespace Opus\Scaffold;

/**
 * Scaffold plan for a full OPUS site/application.
 *
 * Contract:
 * - creates the application common layer;
 * - creates starter rubric pages inspired by the historical OPUS demo model;
 * - every visible home block is backed by a declared page/route;
 * - creates local resources and source-agnostic i18n starter strings;
 * - creates a public front controller that renders only declared routes and .score templates;
 * - never imports external dependencies.
 */
final class SiteScaffoldPlan implements ScaffoldPlanInterface
, SiteScaffoldPlanInterface {
    /**
     * Starter pages generated with a new OPUS site.
     *
     * Home aggregates route/page entries. Pages, Articles, Rubriques and Documentation
     * are rubrique pages that demonstrate the expected application structure without
     * esoteric sample content.
     *
     * @return list<array{id:string, path:string, route:string, role:string, label:string, order:int}>
     */
    /**
     * Starter pages generated with the official OPUS security demo.
     *
     * Contract:
     * - visible labels are localized in i18n resources;
     * - technical identifiers are stable lowercase slugs;
     * - every page template is stored directly under application/pages as one .score file;
     * - no page directory is generated under application/pages.
     *
     * @return list<array{id:string,path:string,route:string,role:string,label:string,order:int}>
     */
    private function starterPages(): array
    {
        return [
            ['id' => 'home', 'path' => '/', 'route' => 'home.index', 'role' => 'demo-home', 'label' => 'menu.home', 'order' => 10],
            ['id' => 'security', 'path' => '/security', 'route' => 'security.index', 'role' => 'demo-security', 'label' => 'menu.security', 'order' => 20],
            ['id' => 'lstsar', 'path' => '/lstsar', 'route' => 'lstsar.index', 'role' => 'demo-lstsar', 'label' => 'menu.lstsar', 'order' => 30],
            ['id' => 'architecture', 'path' => '/architecture', 'route' => 'architecture.index', 'role' => 'demo-architecture', 'label' => 'menu.architecture', 'order' => 40],
            ['id' => 'runtime', 'path' => '/runtime', 'route' => 'runtime.index', 'role' => 'demo-runtime', 'label' => 'menu.runtime', 'order' => 50],
            ['id' => 'rendering', 'path' => '/rendering', 'route' => 'rendering.index', 'role' => 'demo-rendering', 'label' => 'menu.rendering', 'order' => 60],
            ['id' => 'devtools', 'path' => '/devtools', 'route' => 'devtools.index', 'role' => 'demo-devtools', 'label' => 'menu.devtools', 'order' => 70],
            ['id' => 'reference', 'path' => '/reference', 'route' => 'reference.index', 'role' => 'demo-reference', 'label' => 'menu.reference', 'order' => 80],
        ];
    }


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
    /**
     * @return list<ScaffoldEntry>
     */
    public function entries(): array
    {
        $site = $this->siteId;

        $directories = [
            "sites/{$site}/application/config",
            "sites/{$site}/application/common/templates/components",
            "sites/{$site}/application/pages",
            "sites/{$site}/resources/i18n",
            "sites/{$site}/resources/themes",
            "sites/{$site}/resources/assets",
            "sites/{$site}/public/assets/css",
            "sites/{$site}/public/assets/js",
            "sites/{$site}/public/assets/img",
        ];

        $entries = array_map(static fn (string $directory): ScaffoldEntry => ScaffoldEntry::directory($directory), $directories);

        $entries[] = ScaffoldEntry::file("sites/{$site}/README.md", $this->readmeContent());
        $entries[] = ScaffoldEntry::file("sites/{$site}/START_HERE.md", $this->startHereContent());
        $entries[] = ScaffoldEntry::file("sites/{$site}/opus-site.json", $this->json([
            'site_id' => $site,
            'type' => 'opus-demo-site',
            'contract' => 'OPUS_SITE_APPLICATION_V1',
            'starter_contract' => 'OPUS_DEMO_SECURITY_LSTSAR_SINGLE_LEVEL_V1',
            'external_dependencies_allowed' => false,
            'framework_duplication_allowed' => false,
            'created_by' => 'composer opus:create-site',
        ]));
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/config/site.json", $this->json([
            'site_id' => $site,
            'site_name' => 'OPUS Demo ' . $site,
            'contract' => 'OPUS_SITE_APPLICATION_V1',
            'starter_contract' => 'OPUS_DEMO_SECURITY_LSTSAR_SINGLE_LEVEL_V1',
            'default_locale' => 'fr',
            'locales' => ['fr', 'en', 'es'],
            'public_root' => 'public',
            'application_root' => 'application',
            'resources_root' => 'resources',
            'common_root' => 'application/common',
            'pages_root' => 'application/pages',
            'home_route' => 'home.index',
        ]));
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/config/pages.json", $this->json($this->pagesConfig($site)));
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/config/routes.json", $this->json($this->routesConfig()));
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/config/fsm.json", $this->json($this->fsmConfig()));
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/config/menu.json", $this->json($this->menuConfig()));
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/config/rubrics.json", $this->json($this->rubricsConfig()));

        $entries[] = ScaffoldEntry::file("sites/{$site}/application/common/templates/layout.score", $this->commonLayoutScore());
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/common/templates/components/header.score", $this->headerScore());
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/common/templates/components/footer.score", $this->footerScore());
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/common/templates/components/powered-by-opus.score", $this->poweredByScore());
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/common/templates/components/menu-item.score", $this->menuItemScore());
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/common/templates/components/language-selector.score", $this->languageSelectorScore());
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/common/templates/components/rubric-card.score", $this->rubricCardScore());

        foreach ($this->starterPages() as $page) {
            $entries[] = ScaffoldEntry::file(
                "sites/{$site}/application/pages/{$page['id']}.score",
                $page['id'] === 'home' ? $this->homePageScore() : $this->rubricPageScore()
            );
        }

        $entries[] = ScaffoldEntry::file("sites/{$site}/resources/i18n/fr.json", $this->json($this->i18nFr()));
        $entries[] = ScaffoldEntry::file("sites/{$site}/resources/i18n/en.json", $this->json($this->i18nEn()));
        $entries[] = ScaffoldEntry::file("sites/{$site}/resources/i18n/es.json", $this->json($this->i18nEs()));
        $entries[] = ScaffoldEntry::file("sites/{$site}/public/assets/css/starter.css", $this->starterCss());
        $entries[] = ScaffoldEntry::file("sites/{$site}/public/index.php", $this->frontControllerContent());

        return $entries;
    }


    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function pagesConfig(string $site): array
    {
        return [
            'contract' => 'OPUS_PAGE_REGISTRY_V1',
            'layout' => 'single-level-score-pages',
            'pages_root' => 'application/pages',
            'pages' => array_map(static fn (array $page): array => [
                'id' => $page['id'],
                'enabled' => true,
                'contract' => 'OPUS_APPLICATION_PAGE_V1',
                'role' => $page['role'],
                'route' => $page['route'],
                'template' => 'application/pages/' . $page['id'] . '.score',
                'created_by' => 'composer opus:create-site',
            ], $this->starterPages()),
        ];
    }


    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function routesConfig(): array
    {
        return [
            'contract' => 'OPUS_ROUTE_REGISTRY_V1',
            'routes' => array_map(fn (array $page): array => [
                'id' => $page['route'],
                'path' => $page['path'],
                'page' => $page['id'],
                'controller' => null,
                'action' => 'index',
                'template' => 'application/pages/' . $page['id'] . '.score',
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
     * @return array<string, mixed>
     */
    private function fsmConfig(): array
    {
        return [
            'contract' => 'OPUS_FSM_REGISTRY_V1',
            'initial_state' => 'HOME',
            'states' => array_map(static fn (array $page): array => [
                'id' => strtoupper($page['id']),
                'page' => $page['id'],
                'route' => $page['route'],
                'role' => $page['role'],
            ], $this->starterPages()),
            'transitions' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function menuConfig(): array
    {
        return [
            'contract' => 'OPUS_MENU_ROUTE_PROJECTION_V1',
            'source' => 'application/config/routes.json',
            'items' => array_map(static fn (array $page): array => [
                'route' => $page['route'],
                'page' => $page['id'],
                'label' => $page['label'],
                'order' => $page['order'],
            ], $this->starterPages()),
        ];
    }


    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function rubricsConfig(): array
    {
        return [
            'contract' => 'OPUS_HOME_DEMO_CARD_ROUTE_PROJECTION_V1',
            'source' => 'application/config/routes.json',
            'rubrics' => array_values(array_map(static fn (array $page): array => [
                'route' => $page['route'],
                'page' => $page['id'],
                'order' => $page['order'],
            ], array_filter($this->starterPages(), static fn (array $page): bool => $page['id'] !== 'home'))),
        ];
    }


    private function readmeContent(): string
    {
        return "# {$this->siteId}\n\nOPUS demo site generated by `composer opus:create-site`.\n\nThis scaffold is a professional OPUS demonstration focused on secure application architecture: FSM, ACL, SSO, LSTSAR, declared routes, .score rendering and local-only developer tools.\n\n## Contract\n\n- Application pages are single-level `.score` files under `application/pages`.\n- Visible menu labels are localized in `resources/i18n`.\n- Routes are declared in `application/config/routes.json`.\n- Security capabilities are demonstrated as contracts: FSM + ACL + SSO + audit.\n- LSTSAR means Load → Secure → Transform → Store → Audit → Report.\n- The profiler is a local development tool served by `composer opus:serve-site`, never a public site feature.\n- External dependencies are forbidden unless an explicit ADR allows them.\n\nRead `START_HERE.md` before modifying the site.\n";
    }


    private function startHereContent(): string
    {
        return str_replace('{{ site_id }}', $this->siteId, <<<'MD'
# Start here

This OPUS demo site was generated by `composer opus:create-site`.

## Edit map

Route -> single-level score page -> i18n -> assets.

| Need | Source of truth | Generated path |
| --- | --- | --- |
| Public route | Route registry | `application/config/routes.json` |
| Visible menu item | Route/menu projection | `application/config/menu.json` |
| Home demo card | Rubric projection | `application/config/rubrics.json` |
| Page markup | Score page | `application/pages/<slug>.score` |
| Shared layout | Common score template | `application/common/templates/layout.score` |
| Shared components | Common score components | `application/common/templates/components/*.score` |
| Localized text | I18N resources | `resources/i18n/fr.json`, `en.json`, `es.json` |
| Visual styling | Public asset | `public/assets/css/starter.css` |

## Single-level pages

The generated site intentionally keeps pages flat:

```text
application/pages/home.score
application/pages/security.score
application/pages/lstsar.score
application/pages/architecture.score
application/pages/runtime.score
application/pages/rendering.score
application/pages/devtools.score
application/pages/reference.score
```

No page directory is generated under `application/pages`.

## Security focus

The demo presents OPUS as a secure application framework:

- FSM controls allowed states and transitions.
- ACL makes access rules explicit.
- SSO centralizes identity and claims.
- LSTSAR documents the data trust pipeline.
- Audit and reports make decisions explainable.

## LSTSAR

Load → Secure → Transform → Store → Audit → Report.

## Score syntax reminder

- `{{ variable }}` renders escaped text.
- `{{{ variable }}}` renders an explicitly trusted raw HTML slot.
- `[[ include: path/to/component.score ]]` includes a score component.
- `[[ ignore ]] ... [[ endignore ]]` keeps a block in the template source without rendering it.

## Rules

- No wild page creation.
- No route without a declared page.
- No page directory under `application/pages`.
- No business HTML concatenation in controllers or services.
- No duplicated framework.
- No external dependency unless contractually approved.
MD);
    }


    private function commonLayoutScore(): string
    {
        return <<<'SCORE'
<!doctype html>
<html lang="{{ lang }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ page.title }}</title>
  <link rel="stylesheet" href="/assets/css/starter.css">
</head>
<body class="opus-starter-site">
  {{{ common.header }}}
  <main class="opus-main" id="main-content">
    {{{ content }}}
  </main>
  {{{ common.footer }}}
</body>
</html>
SCORE;
    }

    private function headerScore(): string
    {
        return <<<'SCORE'
<header class="opus-header" role="banner">
  <div class="opus-header__inner">
    <a class="opus-brand" href="{{ routes.home }}" aria-label="{{ site.name }}">
      <span class="opus-brand__mark">OP</span>
      <span class="opus-brand__copy">
        <strong>{{ site.name }}</strong>
        <span>{{ site.framework }} secure app framework</span>
      </span>
    </a>
    <nav class="opus-nav" aria-label="{{ i18n.menu_label }}">
      {{{ common.menu }}}
    </nav>
    {{{ common.language_selector }}}
    <a class="opus-cta" href="{{ routes.reference }}">{{ i18n.header_cta }}</a>
  </div>
</header>
SCORE;
    }


    private function footerScore(): string
    {
        return <<<'SCORE'
<footer class="opus-footer" role="contentinfo">
  <div class="opus-footer__inner">
    <div>
      {{{ common.powered_by }}}
      <span class="opus-footer__separator" aria-hidden="true">•</span>
      <span>{{ site.copyright }}</span>
    </div>
    <div class="opus-footer__meta">
      <span>{{ i18n.footer_contract }}</span>
      <span class="opus-footer__separator" aria-hidden="true">•</span>
      <a class="opus-footer__link" href="#main-content">{{ i18n.back_to_top }}</a>
    </div>
  </div>
</footer>
SCORE;
    }

    private function poweredByScore(): string
    {
        return <<<'SCORE'
<span class="opus-powered">Powered by OPUS</span>
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
<form class="opus-lang" action="{{ request.path }}" method="get" aria-label="{{ i18n.language_selector_label }}">
  <label class="opus-lang__label" for="opus-lang-select">{{ i18n.language_short_label }}</label>
  <select id="opus-lang-select" class="opus-lang__select" name="lang" onchange="this.form.submit()">
    {{{ common.language_options }}}
  </select>
</form>
SCORE;
    }

    private function rubricCardScore(): string
    {
        return <<<'SCORE'
<a class="opus-rubric-card" href="{{ rubric.path }}" data-page="{{ rubric.page }}">
  <span class="opus-rubric-card__kicker">{{ rubric.kicker }}</span>
  <strong>{{ rubric.title }}</strong>
  <span>{{ rubric.description }}</span>
  <em>{{ i18n.open_rubric }}</em>
</a>
SCORE;
    }

    private function pageReadmeContent(string $pageId, string $role): string
    {
        $contentKey = $this->pageContentKey($pageId);

        return "# {$pageId} page\n\nGenerated by `composer opus:create-site`.\n\n## Responsibility\n\nRole: `{$role}`.\n\n- Owns its declared route.\n- Renders through `templates/pages/index.score`.\n- Uses localized strings in `resources/i18n/<locale>.json` with the `{$contentKey}.` key prefix.\n- Keeps page markup in `.score` templates.\n- Keeps orchestration in the controller, data preparation in services and render-ready state in view-models.\n- Demonstrates the expected OPUS page structure without JSON page layout.\n\n## Safe edit path\n\n1. Find the route in `application/config/routes.json`.\n2. Confirm this page is declared in `application/config/pages.json`.\n3. Edit `application/pages/{$pageId}/templates/pages/index.score` for markup.\n4. Edit `resources/i18n/<locale>.json` for text.\n5. Edit page-specific assets under this page only when the style belongs to this page.\n\nPatch it for the project real needs after generation.\n";
    }

    private function controllerContent(string $pageId): string
    {
        $namespace = $this->siteNamespace();

        return <<<PHP
<?php
declare(strict_types=1);

namespace OpusSite\\{$namespace}\\{$pageId}\\Controller;

/**
 * Generated {$pageId} controller skeleton.
 *
 * Contract:
 * - controller orchestrates the page use case;
 * - it does not render HTML directly;
 * - rendering belongs to .score templates.
 */
final class {$pageId}Controller
{
    public function index(): void
    {
        // Implement project-specific orchestration here.
        // Do not concatenate HTML in this controller.
    }
}
PHP;
    }

    private function serviceContent(string $pageId): string
    {
        $namespace = $this->siteNamespace();

        return <<<PHP
<?php
declare(strict_types=1);

namespace OpusSite\\{$namespace}\\{$pageId}\\Service;

/**
 * Generated {$pageId} service skeleton.
 *
 * Contract:
 * - service prepares/validates page data;
 * - service does not render HTML;
 * - service returns data for a view-model or response model.
 */
final class {$pageId}PageService
{
    /**
     * @return array<string, string>
     */
    public function loadStarterData(): array
    {
        return [
            'status' => 'starter',
        ];
    }
}
PHP;
    }

    private function viewModelContent(string $pageId): string
    {
        $namespace = $this->siteNamespace();

        return <<<PHP
<?php
declare(strict_types=1);

namespace OpusSite\\{$namespace}\\{$pageId}\\ViewModel;

/**
 * Generated {$pageId} view-model skeleton.
 *
 * Contract:
 * - view-model contains render-ready state;
 * - no business computation here;
 * - no HTML concatenation here.
 */
final class {$pageId}PageViewModel
{
    /**
     * @param array<string, mixed> \$data
     */
    public function __construct(private readonly array \$data)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return \$this->data;
    }
}
PHP;
    }

    private function pageLayoutScore(string $pageId): string
    {
        return <<<SCORE
<section class="opus-page" data-page="{$pageId}">
  {{{ content }}}
</section>
SCORE;
    }

    private function homePageScore(): string
    {
        return <<<'SCORE'
<section class="opus-hero">
  <div class="opus-shell opus-hero__inner">
    <div class="opus-hero__copy">
      <p class="opus-kicker">{{ page.kicker }}</p>
      <h1>{{ page.title }}</h1>
      <p class="opus-lead">{{ page.subtitle }}</p>
      <div class="opus-hero__actions">
        <a class="opus-button opus-button--primary" href="{{ routes.security }}">{{ i18n.explore_security }}</a>
        <a class="opus-button" href="{{ routes.lstsar }}">{{ i18n.explore_lstsar }}</a>
      </div>
    </div>
    <aside class="opus-hero__summary" aria-label="{{ i18n.contract_label }}">
      <p class="opus-card__label">{{ i18n.contract_label }}</p>
      <div class="opus-contract-list">
        <span>{{ i18n.contract_fsm }}</span>
        <span>{{ i18n.contract_acl }}</span>
        <span>{{ i18n.contract_sso }}</span>
        <span>{{ i18n.contract_lstsar }}</span>
        [[ignore]]
        <span>{{ i18n.contract_no_wild }}</span>
        [[endignore]]
      </div>
    </aside>
  </div>
</section>

<section class="opus-section">
  <div class="opus-shell">
    <div class="opus-section__title">
      <p class="opus-kicker">{{ i18n.starter_map }}</p>
      <h2>{{ page.section_title }}</h2>
      <p>{{ page.section_intro }}</p>
    </div>
    <div class="opus-rubric-grid">
      {{{ home.rubric_cards }}}
    </div>
  </div>
</section>
SCORE;
    }


    private function rubricPageScore(): string
    {
        return <<<'SCORE'
<section class="opus-page-hero">
  <div class="opus-shell opus-page-hero__inner">
    <p class="opus-kicker">{{ page.kicker }}</p>
    <h1>{{ page.title }}</h1>
    <p>{{ page.subtitle }}</p>
  </div>
</section>

<section class="opus-section opus-section--compact">
  <div class="opus-shell opus-detail-grid">
    <article class="opus-detail-panel">
      <h2>{{ page.primary_title }}</h2>
      <p>{{ page.primary_text }}</p>
    </article>
    <article class="opus-detail-panel">
      <h2>{{ page.secondary_title }}</h2>
      <p>{{ page.secondary_text }}</p>
    </article>
  </div>
</section>
SCORE;
    }


    /**
     * @return array<string, string>
     */
    /**
     * @return array<string, string>
     */
    private function i18nFr(): array
    {
        return [
            'menu_label' => 'Navigation principale',
            'menu.home' => 'Accueil',
            'menu.security' => 'Sécurité',
            'menu.lstsar' => 'LSTSAR',
            'menu.architecture' => 'Architecture',
            'menu.runtime' => 'Runtime',
            'menu.rendering' => 'Rendu',
            'menu.devtools' => 'DevTools',
            'menu.reference' => 'Référence',
            'header_cta' => 'Référence',
            'explore_security' => 'Voir la sécurité',
            'explore_lstsar' => 'Découvrir LSTSAR',
            'contract_label' => 'Contrat OPUS',
            'contract_fsm' => 'FSM : états et transitions autorisés',
            'contract_acl' => 'ACL : droits explicites par route',
            'contract_sso' => 'SSO : identité et claims centralisés',
            'contract_lstsar' => 'LSTSAR : pipeline de confiance des données',
            'contract_no_wild' => 'Aucune page sauvage',
            'starter_map' => 'Potentiel OPUS',
            'open_rubric' => 'Explorer',
            'footer_contract' => 'Démo OPUS sécurisée',
            'back_to_top' => 'Retour haut',
            'language_selector_label' => 'Sélecteur de langue',
            'language_short_label' => 'Langue',
            'language.fr' => 'Français',
            'language.en' => 'Anglais',
            'language.es' => 'Espagnol',

            'home.kicker' => 'Framework applicatif sécurisé',
            'home.title' => 'OPUS révèle le potentiel d’un site industriel',
            'home.subtitle' => 'Une démo centrée sur la sécurité, les contrats, le runtime, le rendu .score, le profiler local et le pipeline LSTSAR.',
            'home.section_title' => 'Une vitrine claire des capacités OPUS.',
            'home.section_intro' => 'Chaque carte ouvre une capacité structurante du framework, sans mélanger l’arborescence technique et le vocabulaire produit.',

            'security.kicker' => 'Sécurité',
            'security.title' => 'FSM + ACL + SSO',
            'security.subtitle' => 'OPUS traite une route comme un contrat sécurisé, pas comme une simple URL.',
            'security.description' => 'États, droits, identité, claims et refus explicites.',
            'security.primary_title' => 'Décision d’accès explicite',
            'security.primary_text' => 'Une route peut être liée à un état FSM, une transition autorisée, une règle ACL, une identité SSO et une trace audit.',
            'security.secondary_title' => 'Démo sans secret externe',
            'security.secondary_text' => 'La démo présente les contrats SSO et les claims sans imposer de fournisseur OAuth2, OIDC ou SAML.',

            'lstsar.kicker' => 'Pipeline de confiance',
            'lstsar.title' => 'LSTSAR',
            'lstsar.subtitle' => 'Load → Secure → Transform → Store → Audit → Report.',
            'lstsar.description' => 'Un modèle de traitement sécurisé pour les données entrantes et sortantes.',
            'lstsar.primary_title' => 'Aucune donnée ne traverse OPUS directement',
            'lstsar.primary_text' => 'Chaque donnée est chargée, sécurisée, transformée, stockée, auditée puis rapportée selon un contrat explicite.',
            'lstsar.secondary_title' => 'Statut architectural',
            'lstsar.secondary_text' => 'LSTSAR est présenté comme pilier OPUS à implémenter dans le runtime, sans prétendre à un moteur déjà branché.',

            'architecture.kicker' => 'Architecture',
            'architecture.title' => 'Un framework modulaire',
            'architecture.subtitle' => 'Kernel, runtime, routing, templates, modules, contrats et outils de développement restent séparés.',
            'architecture.description' => 'Séparation stricte des responsabilités.',
            'architecture.primary_title' => 'Lisibilité industrielle',
            'architecture.primary_text' => 'Le site généré garde les pages à un seul niveau et place les composants communs dans une zone commune explicite.',
            'architecture.secondary_title' => 'Pas de fallback silencieux',
            'architecture.secondary_text' => 'Un contrat manquant, une locale absente ou une route inconnue doit produire une erreur claire.',

            'runtime.kicker' => 'Runtime',
            'runtime.title' => 'Routes déclarées et états maîtrisés',
            'runtime.subtitle' => 'Le runtime relie requête, route, état, politique et rendu.',
            'runtime.description' => 'Exécution explicite et traçable.',
            'runtime.primary_title' => 'FSM configurée',
            'runtime.primary_text' => 'Les états et transitions ne doivent pas être codés en dur dans le runtime mais déclarés par configuration.',
            'runtime.secondary_title' => 'Refus compréhensible',
            'runtime.secondary_text' => 'Une transition interdite ou une règle ACL non satisfaite doit expliquer la raison du refus.',

            'rendering.kicker' => 'Rendu',
            'rendering.title' => 'Templates .score',
            'rendering.subtitle' => 'Le HTML appartient aux templates, les données aux view-models et aux services.',
            'rendering.description' => 'Rendu contrôlé, composants et slots explicites.',
            'rendering.primary_title' => 'Source lisible',
            'rendering.primary_text' => 'Les pages de démonstration sont des fichiers .score plats, faciles à ouvrir et à modifier.',
            'rendering.secondary_title' => 'Blocs conservés',
            'rendering.secondary_text' => 'Les blocs [[ignore]] restent dans les fichiers générés pour documenter le template sans polluer le rendu.',

            'devtools.kicker' => 'DevTools',
            'devtools.title' => 'Profiler local',
            'devtools.subtitle' => 'Comme tout profiler professionnel, le profiler OPUS est un outil de développement, jamais une fonctionnalité publique.',
            'devtools.description' => 'Serveur interne, toolbar sur demande et traces locales.',
            'devtools.primary_title' => 'Activation volontaire',
            'devtools.primary_text' => 'Le site reste propre sur / et la toolbar apparaît seulement avec ?profiler=1 sur le serveur local.',
            'devtools.secondary_title' => 'Interface dédiée',
            'devtools.secondary_text' => '/_opus/profiler expose les traces uniquement dans le contexte local/dev.',

            'reference.kicker' => 'Référence',
            'reference.title' => 'Contrats et documentation',
            'reference.subtitle' => 'La référence rassemble les conventions de génération, de sécurité, de rendu et de runtime.',
            'reference.description' => 'Point d’entrée pour la documentation développeur.',
            'reference.primary_title' => 'Contrats visibles',
            'reference.primary_text' => 'Routes, pages, menu, rubriques, locales et fichiers générés sont décrits par des manifests lisibles.',
            'reference.secondary_title' => 'Base d’industrialisation',
            'reference.secondary_text' => 'La démo doit rester honnête : elle montre les capacités branchées et les piliers architecturaux à implémenter.',
        ];
    }


    /**
     * @return array<string, string>
     */
    /**
     * @return array<string, string>
     */
    private function i18nEn(): array
    {
        return [
            'menu_label' => 'Main navigation',
            'menu.home' => 'Home',
            'menu.security' => 'Security',
            'menu.lstsar' => 'LSTSAR',
            'menu.architecture' => 'Architecture',
            'menu.runtime' => 'Runtime',
            'menu.rendering' => 'Rendering',
            'menu.devtools' => 'DevTools',
            'menu.reference' => 'Reference',
            'header_cta' => 'Reference',
            'explore_security' => 'View security',
            'explore_lstsar' => 'Discover LSTSAR',
            'contract_label' => 'OPUS contract',
            'contract_fsm' => 'FSM: authorized states and transitions',
            'contract_acl' => 'ACL: explicit route permissions',
            'contract_sso' => 'SSO: centralized identity and claims',
            'contract_lstsar' => 'LSTSAR: trusted data pipeline',
            'contract_no_wild' => 'No wild pages',
            'starter_map' => 'OPUS potential',
            'open_rubric' => 'Explore',
            'footer_contract' => 'Secure OPUS demo',
            'back_to_top' => 'Back to top',
            'language_selector_label' => 'Language selector',
            'language_short_label' => 'Language',
            'language.fr' => 'French',
            'language.en' => 'English',
            'language.es' => 'Spanish',

            'home.kicker' => 'Secure application framework',
            'home.title' => 'OPUS shows the potential of an industrial site',
            'home.subtitle' => 'A demo focused on security, contracts, runtime, .score rendering, the local profiler and the LSTSAR pipeline.',
            'home.section_title' => 'A clear showcase of OPUS capabilities.',
            'home.section_intro' => 'Each card opens a structural framework capability without mixing technical tree names and product vocabulary.',

            'security.kicker' => 'Security',
            'security.title' => 'FSM + ACL + SSO',
            'security.subtitle' => 'OPUS treats a route as a security contract, not just a URL.',
            'security.description' => 'States, permissions, identity, claims and explicit denials.',
            'security.primary_title' => 'Explicit access decision',
            'security.primary_text' => 'A route can be linked to an FSM state, an authorized transition, an ACL rule, an SSO identity and an audit trace.',
            'security.secondary_title' => 'Demo without external secrets',
            'security.secondary_text' => 'The demo presents SSO contracts and claims without imposing an OAuth2, OIDC or SAML provider.',

            'lstsar.kicker' => 'Trust pipeline',
            'lstsar.title' => 'LSTSAR',
            'lstsar.subtitle' => 'Load → Secure → Transform → Store → Audit → Report.',
            'lstsar.description' => 'A secure processing model for inbound and outbound data.',
            'lstsar.primary_title' => 'No data crosses OPUS directly',
            'lstsar.primary_text' => 'Each piece of data is loaded, secured, transformed, stored, audited and reported through an explicit contract.',
            'lstsar.secondary_title' => 'Architectural status',
            'lstsar.secondary_text' => 'LSTSAR is presented as an OPUS pillar to implement in the runtime, without claiming that the engine is already connected.',

            'architecture.kicker' => 'Architecture',
            'architecture.title' => 'A modular framework',
            'architecture.subtitle' => 'Kernel, runtime, routing, templates, modules, contracts and developer tools stay separated.',
            'architecture.description' => 'Strict separation of responsibilities.',
            'architecture.primary_title' => 'Industrial readability',
            'architecture.primary_text' => 'The generated site keeps pages at one level and puts shared components in an explicit common area.',
            'architecture.secondary_title' => 'No silent fallback',
            'architecture.secondary_text' => 'A missing contract, locale or route must produce a clear error.',

            'runtime.kicker' => 'Runtime',
            'runtime.title' => 'Declared routes and controlled states',
            'runtime.subtitle' => 'The runtime links request, route, state, policy and rendering.',
            'runtime.description' => 'Explicit and traceable execution.',
            'runtime.primary_title' => 'Configured FSM',
            'runtime.primary_text' => 'States and transitions must not be hardcoded into the runtime; they belong in configuration.',
            'runtime.secondary_title' => 'Understandable denial',
            'runtime.secondary_text' => 'A forbidden transition or missing ACL rule must explain why access was denied.',

            'rendering.kicker' => 'Rendering',
            'rendering.title' => '.score templates',
            'rendering.subtitle' => 'HTML belongs in templates; data belongs in view-models and services.',
            'rendering.description' => 'Controlled rendering, components and explicit slots.',
            'rendering.primary_title' => 'Readable source',
            'rendering.primary_text' => 'Demo pages are flat .score files that are easy to open and edit.',
            'rendering.secondary_title' => 'Preserved blocks',
            'rendering.secondary_text' => '[[ignore]] blocks remain in generated files to document the template without polluting output.',

            'devtools.kicker' => 'DevTools',
            'devtools.title' => 'Local profiler',
            'devtools.subtitle' => 'Like any professional profiler, the OPUS profiler is a development tool, never a public feature.',
            'devtools.description' => 'Internal server, opt-in toolbar and local traces.',
            'devtools.primary_title' => 'Voluntary activation',
            'devtools.primary_text' => 'The site stays clean on / and the toolbar appears only with ?profiler=1 on the local server.',
            'devtools.secondary_title' => 'Dedicated interface',
            'devtools.secondary_text' => '/_opus/profiler exposes traces only in the local/dev context.',

            'reference.kicker' => 'Reference',
            'reference.title' => 'Contracts and documentation',
            'reference.subtitle' => 'The reference gathers generation, security, rendering and runtime conventions.',
            'reference.description' => 'Entry point for developer documentation.',
            'reference.primary_title' => 'Visible contracts',
            'reference.primary_text' => 'Routes, pages, menu, rubrics, locales and generated files are described by readable manifests.',
            'reference.secondary_title' => 'Industrialization base',
            'reference.secondary_text' => 'The demo must stay honest: it shows connected capabilities and architectural pillars still to implement.',
        ];
    }


    /**
     * @return array<string, string>
     */
    /**
     * @return array<string, string>
     */
    private function i18nEs(): array
    {
        return [
            'menu_label' => 'Navegación principal',
            'menu.home' => 'Inicio',
            'menu.security' => 'Seguridad',
            'menu.lstsar' => 'LSTSAR',
            'menu.architecture' => 'Arquitectura',
            'menu.runtime' => 'Runtime',
            'menu.rendering' => 'Renderizado',
            'menu.devtools' => 'DevTools',
            'menu.reference' => 'Referencia',
            'header_cta' => 'Referencia',
            'explore_security' => 'Ver seguridad',
            'explore_lstsar' => 'Descubrir LSTSAR',
            'contract_label' => 'Contrato OPUS',
            'contract_fsm' => 'FSM: estados y transiciones autorizadas',
            'contract_acl' => 'ACL: permisos explícitos por ruta',
            'contract_sso' => 'SSO: identidad y claims centralizados',
            'contract_lstsar' => 'LSTSAR: pipeline de confianza de datos',
            'contract_no_wild' => 'Sin páginas salvajes',
            'starter_map' => 'Potencial OPUS',
            'open_rubric' => 'Explorar',
            'footer_contract' => 'Demo segura de OPUS',
            'back_to_top' => 'Volver arriba',
            'language_selector_label' => 'Selector de idioma',
            'language_short_label' => 'Idioma',
            'language.fr' => 'Francés',
            'language.en' => 'Inglés',
            'language.es' => 'Español',

            'home.kicker' => 'Framework aplicativo seguro',
            'home.title' => 'OPUS muestra el potencial de un sitio industrial',
            'home.subtitle' => 'Una demo centrada en seguridad, contratos, runtime, renderizado .score, profiler local y pipeline LSTSAR.',
            'home.section_title' => 'Una vitrina clara de las capacidades de OPUS.',
            'home.section_intro' => 'Cada tarjeta abre una capacidad estructural del framework sin mezclar el árbol técnico con el vocabulario de producto.',

            'security.kicker' => 'Seguridad',
            'security.title' => 'FSM + ACL + SSO',
            'security.subtitle' => 'OPUS trata una ruta como un contrato de seguridad, no como una simple URL.',
            'security.description' => 'Estados, permisos, identidad, claims y rechazos explícitos.',
            'security.primary_title' => 'Decisión de acceso explícita',
            'security.primary_text' => 'Una ruta puede vincularse a un estado FSM, una transición autorizada, una regla ACL, una identidad SSO y una traza de auditoría.',
            'security.secondary_title' => 'Demo sin secretos externos',
            'security.secondary_text' => 'La demo presenta contratos SSO y claims sin imponer un proveedor OAuth2, OIDC o SAML.',

            'lstsar.kicker' => 'Pipeline de confianza',
            'lstsar.title' => 'LSTSAR',
            'lstsar.subtitle' => 'Load → Secure → Transform → Store → Audit → Report.',
            'lstsar.description' => 'Un modelo de procesamiento seguro para datos entrantes y salientes.',
            'lstsar.primary_title' => 'Ningún dato cruza OPUS directamente',
            'lstsar.primary_text' => 'Cada dato se carga, se protege, se transforma, se almacena, se audita y se reporta mediante un contrato explícito.',
            'lstsar.secondary_title' => 'Estado arquitectónico',
            'lstsar.secondary_text' => 'LSTSAR se presenta como un pilar OPUS por implementar en el runtime, sin afirmar que el motor ya esté conectado.',

            'architecture.kicker' => 'Arquitectura',
            'architecture.title' => 'Un framework modular',
            'architecture.subtitle' => 'Kernel, runtime, routing, templates, módulos, contratos y herramientas de desarrollo permanecen separados.',
            'architecture.description' => 'Separación estricta de responsabilidades.',
            'architecture.primary_title' => 'Legibilidad industrial',
            'architecture.primary_text' => 'El sitio generado mantiene las páginas en un solo nivel y coloca los componentes compartidos en una zona común explícita.',
            'architecture.secondary_title' => 'Sin fallback silencioso',
            'architecture.secondary_text' => 'Un contrato, locale o ruta ausente debe producir un error claro.',

            'runtime.kicker' => 'Runtime',
            'runtime.title' => 'Rutas declaradas y estados controlados',
            'runtime.subtitle' => 'El runtime conecta petición, ruta, estado, política y renderizado.',
            'runtime.description' => 'Ejecución explícita y trazable.',
            'runtime.primary_title' => 'FSM configurada',
            'runtime.primary_text' => 'Los estados y transiciones no deben estar codificados en el runtime; pertenecen a la configuración.',
            'runtime.secondary_title' => 'Rechazo comprensible',
            'runtime.secondary_text' => 'Una transición prohibida o una regla ACL ausente debe explicar por qué se denegó el acceso.',

            'rendering.kicker' => 'Renderizado',
            'rendering.title' => 'Templates .score',
            'rendering.subtitle' => 'El HTML pertenece a los templates; los datos pertenecen a view-models y servicios.',
            'rendering.description' => 'Renderizado controlado, componentes y slots explícitos.',
            'rendering.primary_title' => 'Fuente legible',
            'rendering.primary_text' => 'Las páginas de demo son archivos .score planos, fáciles de abrir y modificar.',
            'rendering.secondary_title' => 'Bloques conservados',
            'rendering.secondary_text' => 'Los bloques [[ignore]] permanecen en los archivos generados para documentar el template sin contaminar el renderizado.',

            'devtools.kicker' => 'DevTools',
            'devtools.title' => 'Profiler local',
            'devtools.subtitle' => 'Como todo profiler profesional, el profiler OPUS es una herramienta de desarrollo, nunca una función pública.',
            'devtools.description' => 'Servidor interno, toolbar bajo demanda y trazas locales.',
            'devtools.primary_title' => 'Activación voluntaria',
            'devtools.primary_text' => 'El sitio permanece limpio en / y la toolbar aparece solo con ?profiler=1 en el servidor local.',
            'devtools.secondary_title' => 'Interfaz dedicada',
            'devtools.secondary_text' => '/_opus/profiler expone las trazas únicamente en el contexto local/dev.',

            'reference.kicker' => 'Referencia',
            'reference.title' => 'Contratos y documentación',
            'reference.subtitle' => 'La referencia reúne convenciones de generación, seguridad, renderizado y runtime.',
            'reference.description' => 'Punto de entrada para documentación de desarrollo.',
            'reference.primary_title' => 'Contratos visibles',
            'reference.primary_text' => 'Rutas, páginas, menú, rúbricas, locales y archivos generados se describen mediante manifests legibles.',
            'reference.secondary_title' => 'Base de industrialización',
            'reference.secondary_text' => 'La demo debe ser honesta: muestra capacidades conectadas y pilares arquitectónicos aún por implementar.',
        ];
    }

    private function pageContentFr(string $site, string $pageId): array
    {
        $content = [
            'Home' => [
                'kicker' => 'OPUS starter',
                'title' => 'Nouveau site ' . $site,
                'subtitle' => 'Un squelette professionnel pour démarrer un site modulaire OPUS : pages, articles, rubriques et documentation, sans création sauvage.',
                'section_title' => 'Des rubriques prêtes à spécialiser.',
                'section_intro' => 'Chaque encadré ci-dessous est une route vers un page déclaré. Remplacez le contenu, gardez le contrat.',
            ],
            'Pages' => [
                'kicker' => 'Page Pages',
                'title' => 'Pages éditoriales',
                'subtitle' => 'Point d’entrée pour les contenus statiques, présentations, informations légales ou pages institutionnelles.',
                'description' => 'Structure pour pages simples, propres et localisées.',
                'primary_title' => 'Responsabilité',
                'primary_text' => 'Ce page doit porter les pages éditoriales du site, sans logique métier dispersée dans public/index.php.',
                'secondary_title' => 'À personnaliser',
                'secondary_text' => 'Ajoutez vos contenus dans resources/i18n, puis adaptez les templates .score du page Pages.',
            ],
            'Articles' => [
                'kicker' => 'Page Articles',
                'title' => 'Articles et publications',
                'subtitle' => 'Point d’entrée pour les notes, actualités, annonces produit ou publications longues.',
                'description' => 'Structure pour futures publications et archives.',
                'primary_title' => 'Responsabilité',
                'primary_text' => 'Ce page démontre où placer les contenus publiés, les services de listing et les templates d’article.',
                'secondary_title' => 'À personnaliser',
                'secondary_text' => 'Transformez cette rubrique en vrai flux éditorial, sans dépendance externe imposée.',
            ],
            'Rubriques' => [
                'kicker' => 'Page Rubriques',
                'title' => 'Rubriques applicatives',
                'subtitle' => 'Point d’entrée pour organiser les grandes zones métier du site.',
                'description' => 'Structure pour sections, catégories et espaces fonctionnels.',
                'primary_title' => 'Responsabilité',
                'primary_text' => 'Ce page représente le principe OPUS : une rubrique visible correspond à un page ou une route de page.',
                'secondary_title' => 'À personnaliser',
                'secondary_text' => 'Ajoutez vos propres pages métier avec composer opus:create-page, puis reliez-les par routes.',
            ],
            'Documentation' => [
                'kicker' => 'Page Documentation',
                'title' => 'Documentation du site',
                'subtitle' => 'Point d’entrée pour expliquer la structure générée et guider l’implémentation.',
                'description' => 'Structure pour aide développeur et documentation projet.',
                'primary_title' => 'Responsabilité',
                'primary_text' => 'Ce page doit aider l’équipe à comprendre où placer contenus, templates, routes et pages.',
                'secondary_title' => 'À personnaliser',
                'secondary_text' => 'Remplacez cette aide par votre documentation produit ou projet.',
            ],
        ];

        return $content[$pageId] ?? [];
    }

    /**
     * @return array<string, string>
     */
    private function pageContentEn(string $site, string $pageId): array
    {
        $content = [
            'Home' => [
                'kicker' => 'OPUS starter',
                'title' => 'New site ' . $site,
                'subtitle' => 'A professional skeleton to start an OPUS modular site: pages, articles, rubrics and documentation, without wild page creation.',
                'section_title' => 'Rubrics ready to specialize.',
                'section_intro' => 'Each block below is a route to a declared page. Replace the content, keep the contract.',
            ],
            'Pages' => [
                'kicker' => 'Pages page',
                'title' => 'Editorial pages',
                'subtitle' => 'Entry point for static content, presentations, legal information or institutional pages.',
                'description' => 'Structure for simple, clean and localized pages.',
                'primary_title' => 'Responsibility',
                'primary_text' => 'This page owns editorial pages without scattering page logic into public/index.php.',
                'secondary_title' => 'Customize',
                'secondary_text' => 'Adapt the .score templates and i18n strings. Business data will later come through providers, services and view-models.',
            ],
            'Articles' => [
                'kicker' => 'Articles page',
                'title' => 'Articles and publications',
                'subtitle' => 'Entry point for notes, news, product announcements or long-form publications.',
                'description' => 'Structure for future publications and archives.',
                'primary_title' => 'Responsibility',
                'primary_text' => 'This page shows where published content, listing services and article templates belong.',
                'secondary_title' => 'Customize',
                'secondary_text' => 'Turn this rubric into a real editorial stream without imposing external dependencies.',
            ],
            'Rubriques' => [
                'kicker' => 'Rubrics page',
                'title' => 'Application rubrics',
                'subtitle' => 'Entry point to organize the main business areas of the site.',
                'description' => 'Structure for sections, categories and functional areas.',
                'primary_title' => 'Responsibility',
                'primary_text' => 'This page represents the OPUS principle: a visible rubric maps to a page or page route.',
                'secondary_title' => 'Customize',
                'secondary_text' => 'Add your own business services with composer opus:create-page, then connect them through routes.',
            ],
            'Documentation' => [
                'kicker' => 'Documentation page',
                'title' => 'Site documentation',
                'subtitle' => 'Entry point to explain the generated structure and guide implementation.',
                'description' => 'Structure for developer help and project documentation.',
                'primary_title' => 'Responsibility',
                'primary_text' => 'This page helps the team understand where to place content, templates, routes and pages.',
                'secondary_title' => 'Customize',
                'secondary_text' => 'Replace this help with your product or project documentation.',
            ],
        ];

        return $content[$pageId] ?? [];
    }

    private function starterCss(): string
    {
        return <<<'CSS'
:root {
  color-scheme: dark;
  --opus-bg: #07111f;
  --opus-surface: rgba(16, 28, 47, 0.84);
  --opus-surface-strong: rgba(18, 31, 53, 0.96);
  --opus-border: rgba(148, 170, 216, 0.22);
  --opus-border-strong: rgba(148, 170, 216, 0.38);
  --opus-text: #f6f8ff;
  --opus-muted: #b8c5de;
  --opus-soft: #7e8da9;
  --opus-blue: #5aa7ff;
  --opus-cyan: #6ce3ff;
  --opus-yellow: #fff16a;
  --opus-shadow: 0 18px 50px rgba(0, 0, 0, 0.24);
  font-family: Inter, "Segoe UI", Arial, Helvetica, sans-serif;
}

* { box-sizing: border-box; }
html { scroll-behavior: smooth; }
body.opus-starter-site {
  margin: 0;
  min-height: 100vh;
  padding-top: 76px;
  padding-bottom: 58px;
  background:
    radial-gradient(circle at 84% 4%, rgba(90, 167, 255, 0.14), transparent 26rem),
    radial-gradient(circle at 8% 22%, rgba(108, 227, 255, 0.08), transparent 23rem),
    linear-gradient(135deg, #07111f 0%, #0b1627 46%, #07111f 100%);
  color: var(--opus-text);
}

.opus-shell { width: min(1280px, calc(100% - 56px)); margin: 0 auto; }
.opus-header, .opus-footer {
  position: fixed; left: 0; right: 0; z-index: 20;
  background: rgba(7, 17, 31, 0.84);
  border-color: var(--opus-border);
  backdrop-filter: blur(18px); -webkit-backdrop-filter: blur(18px);
}
.opus-header { top: 0; border-bottom: 1px solid var(--opus-border); }
.opus-footer { bottom: 0; border-top: 1px solid var(--opus-border); }
.opus-header__inner, .opus-footer__inner {
  width: min(1280px, calc(100% - 56px)); margin: 0 auto;
  display: flex; align-items: center; justify-content: space-between; gap: 18px;
}
.opus-header__inner { min-height: 64px; }
.opus-footer__inner { min-height: 46px; color: var(--opus-muted); font-size: .88rem; }
.opus-footer__meta, .opus-footer__inner > div { display: inline-flex; align-items: center; gap: 10px; }

.opus-brand { display: inline-flex; align-items: center; gap: 12px; color: var(--opus-text); text-decoration: none; min-width: 220px; }
.opus-brand__mark { display: grid; width: 40px; height: 40px; place-items: center; border-radius: 13px; background: linear-gradient(145deg, #2767e8, #54d7ff); font-size: .72rem; font-weight: 900; box-shadow: 0 12px 34px rgba(84, 215, 255, .12); }
.opus-brand__copy { display: grid; gap: 1px; }
.opus-brand strong { font-size: 1.02rem; }
.opus-brand span span { color: var(--opus-muted); font-size: .8rem; }

.opus-nav { display: flex; align-items: center; gap: 8px; justify-content: center; flex: 1; }
.opus-nav__link, .opus-cta, .opus-button, .opus-footer__link { color: var(--opus-text); text-decoration: none; }
.opus-nav__link, .opus-cta { display: inline-flex; align-items: center; min-height: 36px; padding: 0 14px; border: 1px solid var(--opus-border); border-radius: 999px; color: var(--opus-muted); font-weight: 760; }
.opus-nav__link:hover, .opus-nav__link--active, .opus-cta { color: var(--opus-text); border-color: rgba(90,167,255,.50); background: rgba(90,167,255,.12); }
.opus-cta { flex: 0 0 auto; background: linear-gradient(135deg, rgba(39,103,232,.40), rgba(84,215,255,.18)); }
.opus-lang { display:inline-flex; align-items:center; gap:8px; flex:0 0 auto; }
.opus-lang__label { color: var(--opus-muted); font-size:.78rem; font-weight:850; letter-spacing:.08em; text-transform:uppercase; }
.opus-lang__select { min-height:36px; border:1px solid var(--opus-border); border-radius:999px; padding:0 34px 0 12px; background:rgba(255,255,255,.045); color:var(--opus-text); font-weight:800; outline:none; }
.opus-lang__select:focus { border-color:rgba(108,227,255,.58); box-shadow:0 0 0 3px rgba(108,227,255,.10); }
.opus-lang__select option { background:#0b1627; color:#f6f8ff; }

.opus-main { min-height: calc(100vh - 134px); }
.opus-hero { padding: 26px 0 18px; }
.opus-hero__inner { display: grid; grid-template-columns: minmax(0, 1fr) minmax(250px, 330px); gap: 16px; align-items: stretch; }
.opus-hero__copy, .opus-hero__summary, .opus-rubric-card, .opus-detail-panel, .opus-page-hero__inner {
  border: 1px solid var(--opus-border); border-radius: 22px; background: var(--opus-surface); box-shadow: var(--opus-shadow);
}
.opus-hero__copy { min-height: 310px; padding: clamp(26px, 3.8vw, 42px); display:flex; flex-direction:column; justify-content:center; position: relative; overflow: hidden; }
.opus-hero__copy::after { content:""; position:absolute; right:-76px; top:-96px; width:250px; height:250px; border-radius:50%; background: rgba(90,167,255,.14); pointer-events:none; }
.opus-kicker, .opus-card__label, .opus-powered, .opus-rubric-card__kicker { color: #72b8ff; font-size: .73rem; font-weight: 900; letter-spacing: .16em; text-transform: uppercase; }
.opus-hero h1, .opus-page-hero h1 { margin: 10px 0 0; font-size: clamp(2.65rem, 5vw, 4.55rem); line-height: .94; letter-spacing: -.06em; }
.opus-lead, .opus-page-hero p, .opus-section__title p, .opus-detail-panel p { color: var(--opus-muted); line-height: 1.58; }
.opus-lead { max-width: 720px; margin: 18px 0 0; font-size: clamp(1rem, 1.35vw, 1.16rem); }
.opus-hero__actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:24px; }
.opus-button { min-height:40px; padding:0 16px; display:inline-flex; align-items:center; border:1px solid var(--opus-border-strong); border-radius:999px; font-weight:800; background:rgba(255,255,255,.04); }
.opus-button--primary { border-color:rgba(108,227,255,.58); background:linear-gradient(135deg, rgba(39,103,232,.36), rgba(108,227,255,.16)); }
.opus-hero__summary { min-height: 310px; padding: 22px; display:flex; flex-direction:column; justify-content:center; }
.opus-contract-list { display:grid; gap:10px; margin-top:15px; }
.opus-contract-list span { display:block; padding:10px 12px; border:1px solid rgba(148,170,216,.18); border-radius:14px; background:rgba(255,255,255,.035); color: var(--opus-muted); font-weight:760; line-height:1.35; }

.opus-section { padding: 14px 0 30px; }
.opus-section--compact { padding-top: 22px; }
.opus-section__title { display:grid; gap:7px; max-width: 720px; margin-bottom: 16px; }
.opus-section__title h2 { margin:0; font-size: clamp(1.9rem, 3.4vw, 2.7rem); letter-spacing:-.05em; }
.opus-section__title p { margin:0; }
.opus-rubric-grid { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:14px; }
.opus-rubric-card { min-height: 158px; padding: 18px; display:flex; flex-direction:column; gap:10px; text-decoration:none; color:var(--opus-text); transition: transform .16s ease, border-color .16s ease, background .16s ease; }
.opus-rubric-card:hover { transform: translateY(-2px); border-color: rgba(108,227,255,.45); background: var(--opus-surface-strong); }
.opus-rubric-card strong { font-size:1.12rem; }
.opus-rubric-card span:not(.opus-rubric-card__kicker) { color:var(--opus-muted); line-height:1.45; }
.opus-rubric-card em { margin-top:auto; color:var(--opus-cyan); font-style:normal; font-weight:800; }

.opus-page-hero { padding: 26px 0 8px; }
.opus-page-hero__inner { padding: 34px; }
.opus-page-hero h1 { font-size: clamp(2.4rem, 4.8vw, 4.1rem); }
.opus-detail-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:16px; }
.opus-detail-panel { padding: 24px; }
.opus-detail-panel h2 { margin:0 0 10px; font-size:1.25rem; }

.opus-footer__separator { color: var(--opus-soft); }

@media (max-width: 1050px) {
  .opus-rubric-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .opus-hero__inner, .opus-detail-grid { grid-template-columns: 1fr; }
  .opus-hero__summary { min-height: auto; }
}
@media (max-width: 760px) {
  body.opus-starter-site { padding-top: 126px; padding-bottom: 92px; }
  .opus-shell, .opus-header__inner, .opus-footer__inner { width: min(100% - 28px, 1280px); }
  .opus-header__inner, .opus-footer__inner { flex-wrap: wrap; padding: 12px 0; }
  .opus-nav { order: 3; width: 100%; justify-content: flex-start; overflow:auto; }
  .opus-rubric-grid { grid-template-columns: 1fr; }
  .opus-hero__copy { min-height: 280px; }
  .opus-hero h1, .opus-page-hero h1 { font-size: clamp(2.25rem, 11vw, 3.3rem); }
}
CSS;
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
$renderer = new ScoreTemplateRenderer($siteRoot);

/**
 * Generated starter front controller.
 *
 * Contract:
 * - resolves declared routes only;
 * - renders with the real Opus\Template\ScoreTemplateRenderer;
 * - generates route-based menu/rubric projections through .score partials;
 * - keeps the current locale across all starter navigation links;
 * - contains no project-specific business logic;
 * - exists so a generated site is immediately visible and self-documented.
 */
function opus_read_json(string $path): array
{
    if (!is_file($path)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'OPUS_STARTER_REQUIRED_FILE_MISSING: ' . $path;
        exit;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'OPUS_STARTER_JSON_INVALID: ' . $path;
        exit;
    }

    return $decoded;
}

function opus_i18n(array $i18n, string $key): string
{
    return is_scalar($i18n[$key] ?? null) ? (string) $i18n[$key] : $key;
}

function opus_starter_page_from_i18n(array $i18n, string $page, string $siteId): array
{
    $prefix = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $page));
    if ($prefix === '') {
        $prefix = strtolower($page);
    }

    $keys = [
        'kicker',
        'title',
        'subtitle',
        'description',
        'section_title',
        'section_intro',
        'primary_title',
        'primary_text',
        'secondary_title',
        'secondary_text',
    ];

    $page = [];
    foreach ($keys as $key) {
        $i18nKey = $prefix . '.' . $key;
        $value = opus_i18n($i18n, $i18nKey);
        if ($value === $i18nKey) {
            $value = '';
        }
        $page[$key] = str_replace('{{ site_id }}', $siteId, $value);
    }

    return $page;
}

function opus_route_url(string $path, string $lang): string
{
    return $path . (str_contains($path, '?') ? '&' : '?') . 'lang=' . rawurlencode($lang);
}

function opus_locale_label(string $locale): string
{
    $nativeLabels = [
        'fr' => 'Français',
        'en' => 'English',
        'de' => 'Deutsch',
        'es' => 'Español',
        'it' => 'Italiano',
        'pl' => 'Polski',
        'uk' => 'Українська',
        'cs' => 'Čeština',
    ];

    return $nativeLabels[$locale] ?? strtoupper($locale);
}

function opus_locale_sort_key(string $locale): string
{
    $sortKeys = [
        'cs' => 'cestina',
        'de' => 'deutsch',
        'en' => 'english',
        'es' => 'espanol',
        'fr' => 'francais',
        'it' => 'italiano',
        'pl' => 'polski',
        'uk' => 'ukrainska',
    ];

    return $sortKeys[$locale] ?? $locale;
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
    $profiler->event('request', 'request.received', [
        'path' => $path,
        'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
    ]);

    return $profiler;
}

function opus_starter_profiler_event(?Profiler $profiler, string $category, string $name, array $context = []): void
{
    if ($profiler === null) {
        return;
    }

    $profiler->event($category, $name, $context);
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

    $siteConfig = opus_read_json($siteRoot . '/application/config/site.json');
    $routesConfig = opus_read_json($siteRoot . '/application/config/routes.json');
    opus_starter_profiler_event($profiler, 'config', 'config.loaded', [
        'site_id' => (string) ($siteConfig['site_id'] ?? ''),
        'route_count' => is_countable($routesConfig['routes'] ?? null) ? count($routesConfig['routes']) : 0,
    ]);
    $route = null;
    foreach (($routesConfig['routes'] ?? []) as $candidate) {
        if (($candidate['path'] ?? null) === $path) {
            $route = $candidate;
            break;
        }
    }

    if (!is_array($route)) {
        opus_starter_profiler_event($profiler, 'route', 'route.not_found', [
            'path' => $path,
        ]);
        opus_starter_profiler_stop($profiler, [
            'status' => 404,
            'error' => 'OPUS_STARTER_ROUTE_NOT_FOUND',
            'path' => $path,
        ]);
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'OPUS_STARTER_ROUTE_NOT_FOUND';
        exit;
    }

    opus_starter_profiler_event($profiler, 'route', 'route.matched', [
        'route_id' => (string) ($route['id'] ?? ''),
        'page' => (string) ($route['page'] ?? ''),
        'template' => (string) ($route['template'] ?? ''),
    ]);

    $locales = $siteConfig['locales'] ?? [];
    if (!is_array($locales)) {
        opus_starter_profiler_event($profiler, 'locale', 'locale.contract_invalid');
        opus_starter_profiler_stop($profiler, [
            'status' => 500,
            'error' => 'OPUS_STARTER_LOCALES_CONTRACT_INVALID',
            'path' => $path,
        ]);
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'OPUS_STARTER_LOCALES_CONTRACT_INVALID';
        exit;
    }

    $locales = array_values(array_filter($locales, 'is_scalar'));
    usort($locales, static fn ($left, $right): int => strcmp(opus_locale_sort_key((string) $left), opus_locale_sort_key((string) $right)));

    $defaultLocale = (string) ($siteConfig['default_locale'] ?? 'fr');
    $queryLocale = isset($_GET['lang']) ? strtolower((string) $_GET['lang']) : '';
    $cookieLocale = isset($_COOKIE['opus_starter_lang']) ? strtolower((string) $_COOKIE['opus_starter_lang']) : '';
    $lang = $defaultLocale;
    if ($queryLocale !== '') {
        $lang = $queryLocale;
    } elseif ($cookieLocale !== '' && in_array($cookieLocale, $locales, true)) {
        $lang = $cookieLocale;
    }

    if (!in_array($lang, $locales, true)) {
        opus_starter_profiler_event($profiler, 'locale', 'locale.unavailable', [
            'requested_locale' => $lang,
        ]);
        opus_starter_profiler_stop($profiler, [
            'status' => 400,
            'error' => 'OPUS_STARTER_LOCALE_UNAVAILABLE',
            'path' => $path,
            'locale' => $lang,
        ]);
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'OPUS_STARTER_LOCALE_UNAVAILABLE';
        exit;
    }

    opus_starter_profiler_event($profiler, 'locale', 'locale.selected', [
        'locale' => $lang,
    ]);

    if ($queryLocale !== '') {
        setcookie('opus_starter_lang', $lang, [
            'expires' => time() + 31536000,
            'path' => '/',
            'samesite' => 'Lax',
        ]);
    }

    $i18n = opus_read_json($siteRoot . '/resources/i18n/' . $lang . '.json');
    opus_starter_profiler_event($profiler, 'i18n', 'dictionary.loaded', [
        'locale' => $lang,
        'key_count' => count($i18n),
    ]);
    $currentPageId = (string) ($route['page'] ?? '');
    $page = opus_starter_page_from_i18n($i18n, $currentPageId, (string) ($siteConfig['site_id'] ?? ''));

    $routeUrls = [];
    foreach (($routesConfig['routes'] ?? []) as $configuredRoute) {
        if (!is_array($configuredRoute)) {
            continue;
        }
        $pageKey = strtolower((string) ($configuredRoute['page'] ?? ''));
        $routeUrls[$pageKey] = opus_route_url((string) ($configuredRoute['path'] ?? '/'), $lang);
    }

    $pageData = [
        'lang' => $lang,
        'request' => [
            'path' => $path,
        ],
        'routes' => $routeUrls,
        'site' => [
            'id' => (string) ($siteConfig['site_id'] ?? ''),
            'name' => (string) ($siteConfig['site_name'] ?? ''),
            'framework' => 'OPUS',
            'copyright' => '© Log&Play / OPUS — Tous droits réservés',
        ],
        'page' => $page,
        'i18n' => $i18n,
        'common' => [],
        'home' => [],
    ];

    $menuHtml = '';
    foreach (($routesConfig['routes'] ?? []) as $menuRoute) {
        if (($menuRoute['show_in_menu'] ?? false) !== true) {
            continue;
        }
        $menuData = $pageData;
        $menuData['menu_item'] = [
            'path' => opus_route_url((string) ($menuRoute['path'] ?? '#'), $lang),
            'label' => opus_i18n($i18n, (string) ($menuRoute['label'] ?? '')),
            'active_class' => (($menuRoute['id'] ?? '') === ($route['id'] ?? '')) ? 'opus-nav__link--active' : '',
        ];
        $menuHtml .= $renderer->render('application/common/templates/components/menu-item.score', $menuData);
    }
    $pageData['common']['menu'] = $menuHtml;
    opus_starter_profiler_event($profiler, 'template', 'menu.rendered', [
        'bytes' => strlen($menuHtml),
    ]);

    $languageOptions = '';
    foreach ($locales as $locale) {
        if (!is_scalar($locale)) {
            continue;
        }
        $localeValue = (string) $locale;
        $selected = $localeValue === $lang ? ' selected' : '';
        $languageOptions .= '<option value="' . htmlspecialchars($localeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' . $selected . '>'
            . htmlspecialchars(opus_locale_label($localeValue), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</option>';
    }
    $pageData['common']['language_options'] = $languageOptions;
    $pageData['common']['language_selector'] = $renderer->render('application/common/templates/components/language-selector.score', $pageData);

    $rubricCards = '';
    foreach (($routesConfig['routes'] ?? []) as $rubricRoute) {
        if (($rubricRoute['show_on_home'] ?? false) !== true) {
            continue;
        }
        $rubricPage = opus_starter_page_from_i18n($i18n, (string) ($rubricRoute['page'] ?? ''), (string) ($siteConfig['site_id'] ?? ''));
        $rubricData = $pageData;
        $rubricData['rubric'] = [
            'path' => opus_route_url((string) ($rubricRoute['path'] ?? '#'), $lang),
            'page' => (string) ($rubricRoute['page'] ?? ''),
            'kicker' => (string) ($rubricPage['kicker'] ?? ''),
            'title' => (string) ($rubricPage['title'] ?? ''),
            'description' => (string) ($rubricPage['description'] ?? $rubricPage['subtitle'] ?? ''),
        ];
        $rubricCards .= $renderer->render('application/common/templates/components/rubric-card.score', $rubricData);
    }
    $pageData['home']['rubric_cards'] = $rubricCards;
    opus_starter_profiler_event($profiler, 'template', 'rubric_cards.rendered', [
        'bytes' => strlen($rubricCards),
    ]);
    $pageData['common']['powered_by'] = $renderer->render('application/common/templates/components/powered-by-opus.score', $pageData);

    $template = str_replace('\\', '/', (string) ($route['template'] ?? ''));
    $pageData['content'] = $renderer->render($template, $pageData);
    opus_starter_profiler_event($profiler, 'template', 'page_template.rendered', [
        'template' => $template,
        'bytes' => strlen($pageData['content']),
    ]);
    $pageData['common']['header'] = $renderer->render('application/common/templates/components/header.score', $pageData);
    $pageData['common']['footer'] = $renderer->render('application/common/templates/components/footer.score', $pageData);

    $layoutHtml = $renderer->render('application/common/templates/layout.score', $pageData);
    opus_starter_profiler_event($profiler, 'template', 'layout.rendered', [
        'template' => 'application/common/templates/layout.score',
        'bytes' => strlen($layoutHtml),
    ]);
    opus_starter_profiler_stop($profiler, [
        'status' => 200,
        'path' => $path,
        'route_id' => (string) ($route['id'] ?? ''),
        'locale' => $lang,
    ]);

    header('Content-Type: text/html; charset=UTF-8');
    echo $layoutHtml;
} catch (Throwable $exception) {
    opus_starter_profiler_event($profiler, 'response', 'response.failed', [
        'exception' => get_class($exception),
        'message' => $exception->getMessage(),
    ]);
    opus_starter_profiler_stop($profiler, [
        'status' => 500,
        'error' => 'OPUS_STARTER_RENDER_FAILED',
    ]);
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'OPUS_STARTER_RENDER_FAILED: ' . $exception->getMessage();
}
PHP;
    }

    private function pageContentKey(string $pageId): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $pageId) ?: $pageId);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(array $data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    private function siteNamespace(): string
    {
        $parts = preg_split('/[^a-zA-Z0-9]+/', $this->siteId) ?: [];
        $namespace = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $namespace .= ucfirst(strtolower($part));
        }

        return $namespace !== '' ? $namespace : 'GeneratedSite';
    }
}
