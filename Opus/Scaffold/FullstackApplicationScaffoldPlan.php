<?php
declare(strict_types=1);

namespace Opus\Scaffold;

/** Rich fullstack OPUS application scaffold plan. */
final class FullstackApplicationScaffoldPlan implements ScaffoldPlanInterface
{
    private function __construct(private readonly string $applicationId)
    {
    }

    public static function forApplication(string $applicationId): self
    {
        return new self($applicationId);
    }

    public function rootRelativePath(): string
    {
        return 'sites/' . $this->applicationId;
    }

    /** @return list<ScaffoldEntry> */
    public function entries(): array
    {
        $app = $this->applicationId;
        $directories = [
            'public/assets/css', 'public/assets/js', 'public/assets/img',
            'public/architecture', 'public/catalog/page-catalog', 'public/components', 'public/security', 'public/backoffice', 'public/documentation', 'public/api/catalog',
            'frontend/views/home', 'frontend/views/architecture', 'frontend/views/catalog-index', 'frontend/views/catalog-detail', 'frontend/views/components', 'frontend/views/security', 'frontend/views/backoffice', 'frontend/views/documentation',
            'frontend/layouts/public', 'frontend/layouts/backoffice',
            'frontend/sections/site-header', 'frontend/sections/rich-hero', 'frontend/sections/home-overview', 'frontend/sections/architecture-map', 'frontend/sections/catalog-grid', 'frontend/sections/catalog-detail', 'frontend/sections/component-library', 'frontend/sections/security-pipeline', 'frontend/sections/backoffice-panel', 'frontend/sections/docs-panel', 'frontend/sections/site-footer',
            'frontend/ui', 'frontend/custom-components', 'frontend/navigation', 'frontend/api-clients', 'frontend/assets/css', 'frontend/assets/js', 'frontend/theme',
            'frontend/fsm/states/views', 'frontend/fsm/transitions', 'frontend/backoffice/dashboard', 'frontend/backoffice/fsm/states', 'frontend/backoffice/fsm/transitions',
            'common/fsm/engine', 'common/fsm/state', 'common/fsm/contract', 'common/fsm/result', 'common/fsm/trace',
            'middle/routes', 'middle/api', 'middle/security', 'middle/contracts', 'middle/fsm', 'middle/fsm/transitions',
            'backend/services/catalog', 'backend/services/navigation', 'backend/services/security-demo', 'backend/services', 'backend/actions', 'backend/repositories',
            'backend/validators', 'backend/policies', 'backend/api-endpoints', 'backend/runners', 'backend/jobs', 'backend/dto', 'backend/viewmodels',
            'backend/fsm/states', 'backend/fsm/transitions', 'backend/services/catalog/fsm/states', 'backend/services/catalog/fsm/transitions',
            'resources/i18n', 'resources/data', 'docs',
        ];

        $entries = array_map(static fn (string $directory): ScaffoldEntry => ScaffoldEntry::directory("sites/{$app}/{$directory}"), $directories);

        $entries[] = ScaffoldEntry::file("sites/{$app}/README.md", $this->readmeContent());
        $entries[] = ScaffoldEntry::file("sites/{$app}/START_HERE.md", $this->startHereContent());
        $entries[] = ScaffoldEntry::file("sites/{$app}/application.opus.json", $this->json($this->applicationContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/middle/routes/routes.json", $this->json($this->routesContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/middle/api/catalog.list.contract.json", $this->json($this->catalogApiContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/middle/security/security.pipeline.json", $this->json($this->securityPipelineContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/middle/fsm/fsm.gates.json", $this->json($this->fsmGateContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/middle/contracts/README.md", $this->middleContractsReadme());
        $entries[] = ScaffoldEntry::file("sites/{$app}/common/fsm/engine/README.md", $this->commonFsmEngineReadme());
        $entries[] = ScaffoldEntry::file("sites/{$app}/common/fsm/state/blocked.states.json", $this->json($this->blockedStatesContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/ui/README.md", $this->frontUiReadme());
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/fsm/states/views/front.view.states.json", $this->json($this->frontViewStatesContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/fsm/transitions/front.ui.actions.transitions.json", $this->json($this->frontUiTransitionsContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/backoffice/dashboard/README.md", $this->backofficeDashboardReadme());
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/backoffice/fsm/states/backoffice.admin.view.states.json", $this->json($this->backofficeViewStatesContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/backoffice/fsm/transitions/backoffice.admin.transitions.json", $this->json($this->backofficeTransitionsContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/middle/fsm/transitions/middle.rest_acl_sso.transitions.json", $this->json($this->middleRestAclSsoTransitionsContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/fsm/transitions/back.execution.transitions.json", $this->json($this->backExecutionTransitionsContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/services/catalog/fsm/transitions/catalog.transitions.json", $this->json($this->catalogModuleTransitionsContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/docs/fsm-pipeline.md", $this->fsmPipelineDoc());

        foreach ($this->views() as $viewId => $view) {
            $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/views/{$viewId}/{$viewId}.view.json", $this->json($view));
            $entries[] = ScaffoldEntry::file("sites/{$app}/backend/viewmodels/{$viewId}.viewmodel.json", $this->json($this->viewModelFor($viewId)));
        }

        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/layouts/public/public.layout.json", $this->json(['contract' => 'OPUS_FRONTEND_LAYOUT_V1', 'id' => 'public', 'template' => 'public.layout.score', 'slots' => ['header', 'hero', 'main', 'footer'], 'nested_layouts_allowed' => true]));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/layouts/public/public.layout.score", $this->publicLayoutScore());
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/layouts/backoffice/backoffice.layout.json", $this->json(['contract' => 'OPUS_FRONTEND_LAYOUT_V1', 'id' => 'backoffice', 'template' => 'backoffice.layout.score', 'slots' => ['header', 'hero', 'main', 'footer'], 'nested_layouts_allowed' => true]));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/layouts/backoffice/backoffice.layout.score", $this->backofficeLayoutScore());

        foreach ($this->sections() as $sectionId => $section) {
            $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/sections/{$sectionId}/{$sectionId}.section.json", $this->json($section['contract']));
            $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/sections/{$sectionId}/{$sectionId}.section.score", $section['score']);
        }

        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/navigation/main.navigation.json", $this->json($this->navigationContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/api-clients/catalog.api-client.json", $this->json(['contract' => 'OPUS_FRONTEND_API_CLIENT_V1', 'id' => 'catalog', 'calls' => [['name' => 'list', 'endpoint' => '/api/catalog', 'method' => 'GET', 'response' => 'CatalogListResponse']], 'business_logic_allowed' => false]));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/api-clients/README.md", "# API clients\n\nFrontend API clients call middle API endpoints and translate request/response DTOs for views and components.\n\nThey must not contain business logic.\n");
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/custom-components/README.md", "# Custom components\n\nStandard components belong to OPUS. Place only application-specific components here.\n");

        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/services/catalog/service.opus.json", $this->json(['contract' => 'OPUS_BACKEND_SERVICE_V1', 'id' => 'catalog', 'role' => 'business-domain', 'frontend_view' => false, 'description' => 'Demonstration page providing structured catalog data to several frontend views through middle API contracts.']));
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/services/catalog/catalog.items.json", $this->json($this->catalogItems()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/services/navigation/service.opus.json", $this->json(['contract' => 'OPUS_BACKEND_SERVICE_V1', 'id' => 'navigation', 'role' => 'business-domain', 'frontend_view' => false]));
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/services/security-demo/service.opus.json", $this->json(['contract' => 'OPUS_BACKEND_SERVICE_V1', 'id' => 'security-demo', 'role' => 'business-domain', 'frontend_view' => false]));
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/api-endpoints/catalog-list.endpoint.json", $this->json(['contract' => 'OPUS_BACKEND_API_ENDPOINT_V1', 'id' => 'catalog.list', 'method' => 'GET', 'path' => '/api/catalog', 'action' => 'ListCatalogItemsAction', 'response' => 'CatalogListResponse']));
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/actions/ListCatalogItemsAction.md", "# ListCatalogItemsAction\n\nBackend action placeholder. It returns catalog data through the middle API boundary. It must not render HTML.\n");
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/services/CatalogService.md", "# CatalogService\n\nBackend service placeholder. It processes catalog data and must not know frontend layout details.\n");
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/repositories/CatalogRepository.md", "# CatalogRepository\n\nBackend repository placeholder. It owns data access for the Catalog page.\n");
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/runners/README.md", "# Runners\n\nBackend runners execute backend processing without a frontend request.\n");
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/jobs/README.md", "# Jobs\n\nBackend jobs contain asynchronous/background processing definitions.\n");
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/dto/README.md", "# DTO\n\nDTO files define request/response contracts crossing the frontend/middle/backend boundary.\n");

        $entries[] = ScaffoldEntry::file("sites/{$app}/resources/i18n/fr.json", $this->json($this->i18nFr()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/resources/i18n/en.json", $this->json($this->i18nEn()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/resources/i18n/es.json", $this->json($this->i18nEs()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/public/assets/css/application.css", $this->applicationCss());
        $entries[] = ScaffoldEntry::file("sites/{$app}/public/index.php", $this->frontControllerContent());
        $entries[] = ScaffoldEntry::file("sites/{$app}/public/architecture/index.php", $this->routeProxyContent(1));
        $entries[] = ScaffoldEntry::file("sites/{$app}/public/catalog/index.php", $this->routeProxyContent(1));
        $entries[] = ScaffoldEntry::file("sites/{$app}/public/catalog/page-catalog/index.php", $this->routeProxyContent(2));
        $entries[] = ScaffoldEntry::file("sites/{$app}/public/components/index.php", $this->routeProxyContent(1));
        $entries[] = ScaffoldEntry::file("sites/{$app}/public/security/index.php", $this->routeProxyContent(1));
        $entries[] = ScaffoldEntry::file("sites/{$app}/public/backoffice/index.php", $this->routeProxyContent(1));
        $entries[] = ScaffoldEntry::file("sites/{$app}/public/documentation/index.php", $this->routeProxyContent(1));
        $entries[] = ScaffoldEntry::file("sites/{$app}/public/api/catalog/index.php", $this->routeProxyContent(2));
        $entries[] = ScaffoldEntry::file("sites/{$app}/docs/architecture.md", $this->architectureDoc());

        return $entries;
    }

    /** @return array<string,mixed> */
    private function applicationContract(): array
    {
        return [
            'application_id' => $this->applicationId,
            'type' => 'opus-fullstack-application',
            'contract' => 'OPUS_FULLSTACK_APPLICATION_V1',
            'front_contract' => 'OPUS_FRONT_VIEWS_LAYOUTS_SECTIONS_COMPONENTS_V1',
            'middle_contract' => 'OPUS_MIDDLE_ROUTING_TRANSPORT_SECURITY_V1',
            'back_contract' => 'OPUS_BACK_BUSINESS_DATA_PROCESSING_V1',
            'standard_components_owner' => 'OPUS',
            'custom_components_owner' => 'application',
            'created_by' => 'composer opus:create-application',
            'frontend_root' => 'frontend',
            'middle_root' => 'middle',
            'backend_root' => 'backend',
            'common_root' => 'common',
            'fsm_processor_contract' => 'OPUS_UI_VIEW_STATE_FSM_PIPELINE_V1',
            'front_view_is_fsm_state' => true,
            'front_action_is_fsm_signal' => true,
            'internal_link_is_fsm_action' => true,
            'external_link_is_explicit_boundary_exit' => true,
            'rest_acl_sso_fsm_chain_required' => true,
            'backoffice_is_front_admin_ui' => true,
            'blocked_state_requires_admin_review' => true,
            'public_root' => 'public',
            'backoffice_is_backend' => false,
            'secure_by_design' => true,
            'clean_by_design' => true,
        ];
    }

    /** @return array<string,array<string,mixed>> */
    private function views(): array
    {
        return [
            'home' => $this->view('home', '/', 'public', [['slot' => 'header', 'section' => 'site-header'], ['slot' => 'hero', 'section' => 'rich-hero'], ['slot' => 'main', 'section' => 'home-overview'], ['slot' => 'footer', 'section' => 'site-footer']]),
            'architecture' => $this->view('architecture', '/architecture', 'public', [['slot' => 'header', 'section' => 'site-header'], ['slot' => 'hero', 'section' => 'rich-hero'], ['slot' => 'main', 'section' => 'architecture-map'], ['slot' => 'footer', 'section' => 'site-footer']]),
            'catalog-index' => $this->view('catalog-index', '/catalog', 'public', [['slot' => 'header', 'section' => 'site-header'], ['slot' => 'hero', 'section' => 'rich-hero'], ['slot' => 'main', 'section' => 'catalog-grid'], ['slot' => 'footer', 'section' => 'site-footer']]),
            'catalog-detail' => $this->view('catalog-detail', '/catalog/page-catalog', 'public', [['slot' => 'header', 'section' => 'site-header'], ['slot' => 'hero', 'section' => 'rich-hero'], ['slot' => 'main', 'section' => 'catalog-detail'], ['slot' => 'footer', 'section' => 'site-footer']]),
            'components' => $this->view('components', '/components', 'public', [['slot' => 'header', 'section' => 'site-header'], ['slot' => 'hero', 'section' => 'rich-hero'], ['slot' => 'main', 'section' => 'component-library'], ['slot' => 'footer', 'section' => 'site-footer']]),
            'security' => $this->view('security', '/security', 'public', [['slot' => 'header', 'section' => 'site-header'], ['slot' => 'hero', 'section' => 'rich-hero'], ['slot' => 'main', 'section' => 'security-pipeline'], ['slot' => 'footer', 'section' => 'site-footer']]),
            'backoffice' => $this->view('backoffice', '/backoffice', 'backoffice', [['slot' => 'header', 'section' => 'site-header'], ['slot' => 'hero', 'section' => 'rich-hero'], ['slot' => 'main', 'section' => 'backoffice-panel'], ['slot' => 'footer', 'section' => 'site-footer']]),
            'documentation' => $this->view('documentation', '/documentation', 'public', [['slot' => 'header', 'section' => 'site-header'], ['slot' => 'hero', 'section' => 'rich-hero'], ['slot' => 'main', 'section' => 'docs-panel'], ['slot' => 'footer', 'section' => 'site-footer']]),
        ];
    }

    /** @param list<array{slot:string,section:string}> $sections @return array<string,mixed> */
    private function view(string $id, string $route, string $layout, array $sections): array
    {
        return ['contract' => 'OPUS_FRONTEND_VIEW_V1', 'id' => $id, 'route' => $route, 'layout' => $layout, 'fsm_state' => $id, 'viewmodel' => $id, 'sections' => $sections, 'state_owner' => 'frontend/fsm/states/views'];
    }

    /** @return array<string,mixed> */
    private function routesContract(): array
    {
        return ['contract' => 'OPUS_MIDDLE_ROUTES_V1', 'routes' => ['/' => ['view' => 'home'], '/architecture' => ['view' => 'architecture'], '/catalog' => ['view' => 'catalog-index'], '/catalog/page-catalog' => ['view' => 'catalog-detail'], '/components' => ['view' => 'components'], '/security' => ['view' => 'security'], '/backoffice' => ['view' => 'backoffice'], '/documentation' => ['view' => 'documentation']]];
    }

    /** @return array<string,mixed> */
    private function catalogApiContract(): array
    {
        return ['contract' => 'OPUS_MIDDLE_API_CONTRACT_V1', 'id' => 'catalog.list', 'path' => '/api/catalog', 'method' => 'GET', 'pipeline' => ['route', 'acl', 'fsm', 'audit'], 'backend_action' => 'ListCatalogItemsAction', 'response' => 'CatalogListResponse'];
    }

    /** @return array<string,mixed> */
    private function securityPipelineContract(): array
    {
        return ['contract' => 'OPUS_MIDDLE_SECURITY_PIPELINE_V1', 'steps' => ['route-match', 'request-dto', 'sso-placeholder', 'acl', 'fsm-gate', 'csrf-for-state-change', 'rate-limit', 'audit', 'backend-action', 'response-dto']];
    }

    /** @return array<string,mixed> */
    private function fsmGateContract(): array
    {
        return ['contract' => 'OPUS_MIDDLE_FSM_GATES_V1', 'gates' => [['id' => 'catalog.read', 'allowed_states' => ['READY'], 'mode' => 'read-only-demo']]];
    }

    /** @return array<string,mixed> */
    private function navigationContract(): array
    {
        return ['contract' => 'OPUS_FRONTEND_NAVIGATION_V1', 'component' => 'OPUS.StandardComponent.Menu', 'items' => [['label' => '@nav.home', 'href' => '/', 'order' => 10], ['label' => '@nav.architecture', 'href' => '/architecture', 'order' => 20], ['label' => '@nav.catalog', 'href' => '/catalog', 'order' => 30], ['label' => '@nav.components', 'href' => '/components', 'order' => 40], ['label' => '@nav.security', 'href' => '/security', 'order' => 50], ['label' => '@nav.backoffice', 'href' => '/backoffice', 'order' => 60], ['label' => '@nav.docs', 'href' => '/documentation', 'order' => 70]]];
    }

    /** @return array<string,mixed> */
    private function catalogItems(): array
    {
        return ['contract' => 'OPUS_BACKEND_CATALOG_DATA_V1', 'items' => [['id' => 'page-catalog', 'title' => '@catalog.page.title', 'text' => '@catalog.page.text', 'href' => '/catalog/page-catalog', 'badge' => '@badge.backend'], ['id' => 'security-pipeline', 'title' => '@catalog.security.title', 'text' => '@catalog.security.text', 'href' => '/security', 'badge' => '@badge.middle'], ['id' => 'component-library', 'title' => '@catalog.components.title', 'text' => '@catalog.components.text', 'href' => '/components', 'badge' => '@badge.front']]];
    }

    /** @return array<string,array{contract:array<string,mixed>,score:string}> */
    private function sections(): array
    {
        return [
            'site-header' => ['contract' => ['contract' => 'OPUS_FRONTEND_SECTION_V1', 'id' => 'site-header', 'components' => [['component' => 'OPUS.StandardComponent.Menu']]], 'score' => $this->siteHeaderScore()],
            'rich-hero' => ['contract' => ['contract' => 'OPUS_FRONTEND_SECTION_V1', 'id' => 'rich-hero', 'components' => [['component' => 'OPUS.StandardComponent.TextBlock'], ['component' => 'OPUS.StandardComponent.Button']]], 'score' => $this->richHeroScore()],
            'home-overview' => ['contract' => ['contract' => 'OPUS_FRONTEND_SECTION_V1', 'id' => 'home-overview', 'components' => [['component' => 'OPUS.StandardComponent.Card']]], 'score' => $this->homeOverviewScore()],
            'architecture-map' => ['contract' => ['contract' => 'OPUS_FRONTEND_SECTION_V1', 'id' => 'architecture-map', 'components' => [['component' => 'OPUS.StandardComponent.Card']]], 'score' => $this->architectureMapScore()],
            'catalog-grid' => ['contract' => ['contract' => 'OPUS_FRONTEND_SECTION_V1', 'id' => 'catalog-grid', 'components' => [['component' => 'OPUS.StandardComponent.Card'], ['component' => 'OPUS.StandardComponent.Menu']]], 'score' => $this->catalogGridScore()],
            'catalog-detail' => ['contract' => ['contract' => 'OPUS_FRONTEND_SECTION_V1', 'id' => 'catalog-detail', 'components' => [['component' => 'OPUS.StandardComponent.Card']]], 'score' => $this->catalogDetailScore()],
            'component-library' => ['contract' => ['contract' => 'OPUS_FRONTEND_SECTION_V1', 'id' => 'component-library', 'components' => [['component' => 'OPUS.StandardComponent.Form'], ['component' => 'OPUS.StandardComponent.Menu'], ['component' => 'OPUS.StandardComponent.Card']]], 'score' => $this->componentLibraryScore()],
            'security-pipeline' => ['contract' => ['contract' => 'OPUS_FRONTEND_SECTION_V1', 'id' => 'security-pipeline', 'components' => [['component' => 'OPUS.StandardComponent.List']]], 'score' => $this->securityPipelineScore()],
            'backoffice-panel' => ['contract' => ['contract' => 'OPUS_FRONTEND_SECTION_V1', 'id' => 'backoffice-panel', 'components' => [['component' => 'OPUS.StandardComponent.Table'], ['component' => 'OPUS.StandardComponent.Form']]], 'score' => $this->backofficePanelScore()],
            'docs-panel' => ['contract' => ['contract' => 'OPUS_FRONTEND_SECTION_V1', 'id' => 'docs-panel', 'components' => [['component' => 'OPUS.StandardComponent.Card']]], 'score' => $this->docsPanelScore()],
            'site-footer' => ['contract' => ['contract' => 'OPUS_FRONTEND_SECTION_V1', 'id' => 'site-footer', 'components' => [['component' => 'OPUS.StandardComponent.TextBlock']]], 'score' => $this->siteFooterScore()],
        ];
    }

    /** @return array<string,mixed> */
    private function viewModelFor(string $viewId): array
    {
        $base = ['contract' => 'OPUS_VIEWMODEL_V1', 'view' => $viewId, 'application' => ['id' => $this->applicationId, 'name' => '@app.name', 'subtitle' => '@app.subtitle'], 'labels' => ['language' => '@label.language', 'open' => '@label.open', 'readMore' => '@label.read_more', 'requestResponse' => '@label.request_response'], 'footer' => ['text' => '@footer.text']];
        $models = [
            'home' => ['hero' => ['kicker' => '@home.kicker', 'title' => '@home.title', 'subtitle' => '@home.subtitle', 'primary' => ['label' => '@home.primary', 'href' => '/architecture'], 'secondary' => ['label' => '@home.secondary', 'href' => '/catalog']], 'cards' => [['title' => '@home.card.front.title', 'text' => '@home.card.front.text'], ['title' => '@home.card.middle.title', 'text' => '@home.card.middle.text'], ['title' => '@home.card.back.title', 'text' => '@home.card.back.text'], ['title' => '@home.card.catalog.title', 'text' => '@home.card.catalog.text']]],
            'architecture' => ['hero' => ['kicker' => '@architecture.kicker', 'title' => '@architecture.title', 'subtitle' => '@architecture.subtitle', 'primary' => ['label' => '@architecture.primary', 'href' => '/security'], 'secondary' => ['label' => '@architecture.secondary', 'href' => '/components']], 'layers' => [['name' => 'OPUS\\Front', 'title' => '@front.title', 'text' => '@front.text'], ['name' => 'OPUS\\Middle', 'title' => '@middle.title', 'text' => '@middle.text'], ['name' => 'OPUS\\Back', 'title' => '@back.title', 'text' => '@back.text']]],
            'catalog-index' => ['hero' => ['kicker' => '@catalog.kicker', 'title' => '@catalog.title', 'subtitle' => '@catalog.subtitle', 'primary' => ['label' => '@catalog.primary', 'href' => '/catalog/page-catalog'], 'secondary' => ['label' => '@catalog.secondary', 'href' => '/api/catalog']], 'catalog' => ['items' => []]],
            'catalog-detail' => ['hero' => ['kicker' => '@catalog.detail.kicker', 'title' => '@catalog.detail.title', 'subtitle' => '@catalog.detail.subtitle', 'primary' => ['label' => '@catalog.detail.primary', 'href' => '/catalog'], 'secondary' => ['label' => '@catalog.detail.secondary', 'href' => '/api/catalog']], 'detail' => ['title' => '@catalog.page.title', 'text' => '@catalog.page.detail', 'facts' => [['label' => '@fact.backend', 'value' => 'backend/services/catalog'], ['label' => '@fact.api', 'value' => '/api/catalog'], ['label' => '@fact.views', 'value' => '/catalog + /catalog/page-catalog']]]],
            'components' => ['hero' => ['kicker' => '@components.kicker', 'title' => '@components.title', 'subtitle' => '@components.subtitle', 'primary' => ['label' => '@components.primary', 'href' => '/catalog'], 'secondary' => ['label' => '@components.secondary', 'href' => '/architecture']], 'componentCards' => [['name' => 'Menu', 'text' => '@component.menu'], ['name' => 'Form', 'text' => '@component.form'], ['name' => 'Input', 'text' => '@component.input'], ['name' => 'Card', 'text' => '@component.card'], ['name' => 'Table', 'text' => '@component.table']]],
            'security' => ['hero' => ['kicker' => '@security.kicker', 'title' => '@security.title', 'subtitle' => '@security.subtitle', 'primary' => ['label' => '@security.primary', 'href' => '/backoffice'], 'secondary' => ['label' => '@security.secondary', 'href' => '/architecture']], 'securitySteps' => [['step' => 'Route', 'text' => '@security.route'], ['step' => 'Request DTO', 'text' => '@security.request'], ['step' => 'ACL / SSO', 'text' => '@security.acl'], ['step' => 'FSM Gate', 'text' => '@security.fsm'], ['step' => 'Audit', 'text' => '@security.audit'], ['step' => 'Backend Action', 'text' => '@security.action']]],
            'backoffice' => ['hero' => ['kicker' => '@backoffice.kicker', 'title' => '@backoffice.title', 'subtitle' => '@backoffice.subtitle', 'primary' => ['label' => '@backoffice.primary', 'href' => '/security'], 'secondary' => ['label' => '@backoffice.secondary', 'href' => '/']], 'backofficeCards' => [['title' => '@backoffice.card.front.title', 'text' => '@backoffice.card.front.text'], ['title' => '@backoffice.card.backend.title', 'text' => '@backoffice.card.backend.text'], ['title' => '@backoffice.card.secure.title', 'text' => '@backoffice.card.secure.text']]],
            'documentation' => ['hero' => ['kicker' => '@docs.kicker', 'title' => '@docs.title', 'subtitle' => '@docs.subtitle', 'primary' => ['label' => '@docs.primary', 'href' => '/architecture'], 'secondary' => ['label' => '@docs.secondary', 'href' => '/catalog']], 'docsCards' => [['title' => 'application.opus.json', 'text' => '@docs.application'], ['title' => 'middle/routes/routes.json', 'text' => '@docs.routes'], ['title' => 'backend/services/catalog', 'text' => '@docs.catalog']]],
        ];

        return array_merge($base, $models[$viewId] ?? []);
    }

    /** @param array<string,mixed> $data */
    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }

    /** @return array<string,mixed> */
    private function frontViewStatesContract(): array
    {
        return [
            'contract' => 'OPUS_FRONT_VIEW_STATES_V1',
            'pipeline_contract' => 'OPUS_UI_VIEW_STATE_FSM_PIPELINE_V1',
            'rule' => 'VIEW_IS_FSM_STATE',
            'owner' => 'frontend/fsm/states/views',
            'states' => [
                ['id' => 'home', 'view' => 'home', 'kind' => 'ui_view', 'blocked' => false],
                ['id' => 'architecture', 'view' => 'architecture', 'kind' => 'ui_view', 'blocked' => false],
                ['id' => 'catalog-index', 'view' => 'catalog-index', 'kind' => 'ui_view', 'blocked' => false],
                ['id' => 'catalog-detail', 'view' => 'catalog-detail', 'kind' => 'ui_view', 'blocked' => false],
                ['id' => 'components', 'view' => 'components', 'kind' => 'ui_view', 'blocked' => false],
                ['id' => 'security', 'view' => 'security', 'kind' => 'ui_view', 'blocked' => false],
                ['id' => 'backoffice', 'view' => 'backoffice', 'kind' => 'admin_ui_view', 'blocked' => false],
                ['id' => 'documentation', 'view' => 'documentation', 'kind' => 'ui_view', 'blocked' => false],
                ['id' => 'login', 'view' => 'login', 'kind' => 'ui_view', 'blocked' => false],
                ['id' => 'forbidden', 'view' => 'forbidden', 'kind' => 'ui_view', 'blocked' => false],
                ['id' => 'blocked', 'view' => 'blocked', 'kind' => 'ui_view', 'blocked' => true],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function frontUiTransitionsContract(): array
    {
        return [
            'contract' => 'OPUS_FRONT_UI_ACTION_TRANSITIONS_V1',
            'pipeline_contract' => 'OPUS_UI_VIEW_STATE_FSM_PIPELINE_V1',
            'layer' => 'FRONT',
            'owner' => 'frontend/fsm/transitions',
            'engine' => 'common/fsm/engine',
            'rules' => [
                'VIEW_IS_STATE',
                'ACTION_IS_SIGNAL',
                'COMPONENT_EMITS_ACTION_BUT_DOES_NOT_CHANGE_STATE_DIRECTLY',
                'INTERNAL_LINK_IS_ACTION',
                'EXTERNAL_LINK_IS_EXPLICIT_BOUNDARY_EXIT',
            ],
            'transitions' => [
                ['from' => 'home', 'action' => 'OPEN_CATALOG', 'signal' => 'front.open_catalog.requested', 'transport' => 'REST', 'requires' => ['MIDDLE_REST', 'MIDDLE_SSO', 'MIDDLE_ACL', 'MIDDLE_FSM_GATE', 'BACK_CATALOG_LIST'], 'on_success' => 'catalog-index', 'on_sso_required' => 'login', 'on_acl_denied' => 'forbidden', 'on_violation' => 'blocked'],
                ['from' => 'catalog-index', 'action' => 'SEARCH', 'signal' => 'front.catalog.search.requested', 'transport' => 'REST', 'requires' => ['MIDDLE_REST', 'MIDDLE_SSO', 'MIDDLE_ACL', 'MIDDLE_FSM_GATE', 'BACK_CATALOG_SEARCH'], 'on_success' => 'catalog-index', 'on_error' => 'blocked'],
                ['from' => 'catalog-index', 'action' => 'OPEN_PRODUCT', 'signal' => 'front.open_product.requested', 'transport' => 'REST', 'requires' => ['MIDDLE_REST', 'MIDDLE_SSO', 'MIDDLE_ACL', 'MIDDLE_FSM_GATE', 'BACK_CATALOG_DETAIL'], 'on_success' => 'catalog-detail', 'on_violation' => 'blocked'],
                ['from' => '*', 'action' => 'EXTERNAL_LINK', 'signal' => 'front.external_link.requested', 'transport' => 'BROWSER_EXTERNAL', 'bypass_opus_pipeline' => true, 'exception_reason' => 'External Link leaves the OPUS application boundary.'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function middleRestAclSsoTransitionsContract(): array
    {
        return [
            'contract' => 'OPUS_MIDDLE_REST_ACL_SSO_FSM_TRANSITIONS_V1',
            'layer' => 'MIDDLE',
            'owner' => 'middle/fsm/transitions',
            'engine' => 'common/fsm/engine',
            'mandatory_chain' => ['REST_ROUTE', 'REQUEST_CONTRACT', 'SSO', 'ACL', 'FSM_GATE', 'AUDIT'],
            'transitions' => [
                ['from' => 'REST_REQUEST_RECEIVED', 'signal' => 'middle.route.match', 'to' => 'ROUTE_MATCHED'],
                ['from' => 'ROUTE_MATCHED', 'signal' => 'middle.request.valid', 'to' => 'REQUEST_CONTRACT_ACCEPTED'],
                ['from' => 'REQUEST_CONTRACT_ACCEPTED', 'signal' => 'middle.sso.ok', 'to' => 'SSO_ACCEPTED'],
                ['from' => 'REQUEST_CONTRACT_ACCEPTED', 'signal' => 'middle.sso.required', 'to' => 'BLOCKED_BY_SSO_REQUIRED'],
                ['from' => 'SSO_ACCEPTED', 'signal' => 'middle.acl.ok', 'to' => 'ACL_ACCEPTED'],
                ['from' => 'SSO_ACCEPTED', 'signal' => 'middle.acl.denied', 'to' => 'BLOCKED_BY_ACL_VIOLATION'],
                ['from' => 'ACL_ACCEPTED', 'signal' => 'middle.fsm.gate.allowed', 'to' => 'BACK_DISPATCH_ALLOWED'],
                ['from' => 'ACL_ACCEPTED', 'signal' => 'middle.fsm.gate.denied', 'to' => 'BLOCKED_BY_INVALID_TRANSITION'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function backExecutionTransitionsContract(): array
    {
        return [
            'contract' => 'OPUS_BACK_EXECUTION_TRANSITIONS_V1',
            'layer' => 'BACK',
            'owner' => 'backend/fsm/transitions',
            'engine' => 'common/fsm/engine',
            'transitions' => [
                ['from' => 'BACK_DISPATCH_ALLOWED', 'signal' => 'back.action.requested', 'to' => 'BACK_ACTION_REQUESTED'],
                ['from' => 'BACK_ACTION_REQUESTED', 'signal' => 'back.service.executed', 'to' => 'BACK_SERVICE_EXECUTED'],
                ['from' => 'BACK_SERVICE_EXECUTED', 'signal' => 'back.result.built', 'to' => 'MIDDLE_RESPONSE_REQUIRED'],
                ['from' => 'BACK_ACTION_REQUESTED', 'signal' => 'back.contract.violation', 'to' => 'BLOCKED_BY_CONTRACT_VIOLATION'],
                ['from' => 'BACK_ACTION_REQUESTED', 'signal' => 'back.runner.failed', 'to' => 'BLOCKED_BY_RUNNER_FAILURE'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function catalogModuleTransitionsContract(): array
    {
        return [
            'contract' => 'OPUS_BACK_PAGE_TRANSITIONS_V1',
            'page' => 'catalog',
            'owner' => 'backend/services/catalog/fsm/transitions',
            'engine' => 'common/fsm/engine',
            'transitions' => [
                ['from' => 'BACK_ACTION_REQUESTED', 'signal' => 'catalog.list.requested', 'to' => 'CATALOG_LIST_REQUESTED'],
                ['from' => 'CATALOG_LIST_REQUESTED', 'signal' => 'catalog.items.loaded', 'to' => 'CATALOG_ITEMS_LOADED'],
                ['from' => 'CATALOG_ITEMS_LOADED', 'signal' => 'catalog.response.ready', 'to' => 'MIDDLE_RESPONSE_REQUIRED'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function backofficeViewStatesContract(): array
    {
        return [
            'contract' => 'OPUS_BACKOFFICE_VIEW_STATES_V1',
            'layer' => 'FRONT',
            'area' => 'Backoffice',
            'rule' => 'DASHBOARD_IS_FRONT_ADMIN_UI_NOT_BACKEND',
            'states' => [
                ['id' => 'AdminDashboardView', 'purpose' => 'Admin overview'],
                ['id' => 'AdminBlockedStatesView', 'purpose' => 'Blocked state review'],
                ['id' => 'AdminTransitionInspectorView', 'purpose' => 'FSM transition inspection'],
                ['id' => 'AdminAuditTrailView', 'purpose' => 'Audit trail'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function backofficeTransitionsContract(): array
    {
        return [
            'contract' => 'OPUS_BACKOFFICE_ADMIN_TRANSITIONS_V1',
            'layer' => 'FRONT',
            'area' => 'Backoffice',
            'owner' => 'frontend/backoffice/fsm/transitions',
            'engine' => 'common/fsm/engine',
            'transitions' => [
                ['from' => 'blocked', 'action' => 'ADMIN_REVIEW_REQUIRED', 'to' => 'AdminBlockedStatesView'],
                ['from' => 'AdminBlockedStatesView', 'action' => 'ADMIN_UNBLOCK', 'to' => 'home'],
                ['from' => 'AdminBlockedStatesView', 'action' => 'ADMIN_REJECT', 'to' => 'forbidden'],
                ['from' => 'AdminBlockedStatesView', 'action' => 'ADMIN_REPAIR_PENDING', 'to' => 'blocked'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function blockedStatesContract(): array
    {
        return [
            'contract' => 'OPUS_BLOCKED_STATES_V1',
            'rule' => 'NO_SILENT_FALLBACK_ON_TRANSGRESSION',
            'backoffice' => 'frontend/backoffice/dashboard',
            'states' => [
                'BLOCKED_BY_INVALID_TRANSITION',
                'BLOCKED_BY_CONTRACT_VIOLATION',
                'BLOCKED_BY_ACL_VIOLATION',
                'BLOCKED_BY_SSO_REQUIRED',
                'BLOCKED_BY_CSRF_FAILURE',
                'BLOCKED_BY_BACK_EXCEPTION',
                'BLOCKED_BY_RUNNER_FAILURE',
                'BLOCKED_BY_DATA_VALIDATION_ERROR',
            ],
        ];
    }

    private function commonFsmEngineReadme(): string
    {
        return "# COMMON FSM Engine\n\nThe engine is shared. It is the processor. It owns no application-specific transition fuel.\n";
    }

    private function frontUiReadme(): string
    {
        return "# FRONT UI\n\nFRONT is the UI layer. A View is an FSM state and an Action is a signal. Components emit actions or display state; they do not mutate state directly.\n";
    }

    private function backofficeDashboardReadme(): string
    {
        return "# Backoffice Dashboard\n\nThe dashboard is FRONT admin UI. It exposes blocked FSM states and lets an administrator review, unblock, reject, repair or audit. It is not BACK.\n";
    }

    private function fsmPipelineDoc(): string
    {
        return <<<'MARKDOWN'
# OPUS generated application FSM pipeline

## Contract

`VIEW = FSM state`, `ACTION = FSM signal`, `COMMON/FSM/Engine = processor`.

Every internal action follows:

```text
FRONT -> MIDDLE -> BACK -> MIDDLE -> FRONT
```

External links are the only explicit boundary-exit exception.

## State diagram

```mermaid
stateDiagram-v2
    [*] --> home
    home --> catalog-index: OPEN_CATALOG / REST + SSO_OK + ACL_OK + BACK_OK
    home --> login: OPEN_CATALOG / SSO_REQUIRED
    home --> forbidden: OPEN_CATALOG / ACL_DENIED
    home --> blocked: OPEN_CATALOG / CONTRACT_VIOLATION
    catalog-index --> catalog-index: SEARCH / BACK_OK + SAME_VIEW
    catalog-index --> catalog-detail: OPEN_PRODUCT / BACK_OK
    catalog-index --> blocked: INVALID_TRANSITION
    catalog-detail --> catalog-index: BACK_TO_CATALOG
    blocked --> AdminBlockedStatesView: ADMIN_REVIEW_REQUIRED
    home --> ExternalBrowser: EXTERNAL_LINK
```

## Sequence diagram

```mermaid
sequenceDiagram
    actor User
    participant UI as FRONT UI View
    participant Engine as COMMON FSM Engine
    participant REST as MIDDLE REST
    participant SSO as MIDDLE SSO
    participant ACL as MIDDLE ACL
    participant BACK as BACK Action/Service
    participant ADMIN as FRONT Backoffice Dashboard

    User->>UI: action
    UI->>Engine: current view state + action signal
    Engine->>REST: internal request
    REST->>SSO: session transition
    SSO->>ACL: permission transition
    ACL->>BACK: backend transition
    BACK-->>ACL: business result
    ACL-->>REST: response contract
    REST-->>Engine: transition result
    alt normal result
        Engine-->>UI: next view state or same view state
    else transgression
        Engine-->>ADMIN: blocked state review required
        Engine-->>UI: blocked/login/forbidden/error view
    end
```
MARKDOWN;
    }

    private function publicLayoutScore(): string
    {
        return <<<'SCORE'
<!doctype html><html lang="{{ runtime.lang }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ page.title }} · {{ application.name }}</title><link rel="stylesheet" href="/assets/css/application.css"></head><body class="opus-app opus-public"><header>{{{ slots.header }}}</header><main>{{{ slots.hero }}}{{{ slots.main }}}</main><footer>{{{ slots.footer }}}</footer></body></html>
SCORE;
    }

    private function backofficeLayoutScore(): string
    {
        return <<<'SCORE'
<!doctype html><html lang="{{ runtime.lang }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ page.title }} · {{ application.name }}</title><link rel="stylesheet" href="/assets/css/application.css"></head><body class="opus-app opus-backoffice"><header>{{{ slots.header }}}</header><main>{{{ slots.hero }}}{{{ slots.main }}}</main><footer>{{{ slots.footer }}}</footer></body></html>
SCORE;
    }

    private function siteHeaderScore(): string
    {
        return <<<'SCORE'
<div class="op-topbar"><a class="brand" href="/?lang={{ runtime.lang }}"><span>OP</span><strong>{{ application.name }}</strong><small>{{ application.subtitle }}</small></a><nav class="main-nav">[[ foreach: navigation.items as item ]]<a href="{{ item.href }}?lang={{ runtime.lang }}">{{ item.label }}</a>[[ endforeach ]]</nav><div class="language-switcher"><b>{{ labels.language }}</b>[[ foreach: runtime.languages as language ]]<a href="{{ runtime.currentPath }}?lang={{ language.id }}">{{ language.label }}</a>[[ endforeach ]]</div></div>
SCORE;
    }

    private function richHeroScore(): string
    {
        return <<<'SCORE'
<section class="hero op-shell"><div><p class="kicker">{{ hero.kicker }}</p><h1>{{ hero.title }}</h1><p class="lead">{{ hero.subtitle }}</p><div class="actions"><a class="primary" href="{{ hero.primary.href }}?lang={{ runtime.lang }}">{{ hero.primary.label }}</a><a class="secondary" href="{{ hero.secondary.href }}?lang={{ runtime.lang }}">{{ hero.secondary.label }}</a></div></div><aside class="contract-card"><p>OPUS_FULLSTACK_APPLICATION_V1</p><strong>Front / Middle / Back</strong><span>{{ labels.requestResponse }}</span></aside></section>
SCORE;
    }

    private function homeOverviewScore(): string
    {
        return <<<'SCORE'
<section class="op-shell section-block"><p class="kicker">{{ page.kicker }}</p><h2>{{ page.title }}</h2><div class="card-grid">[[ foreach: cards as card ]]<article class="card"><h3>{{ card.title }}</h3><p>{{ card.text }}</p></article>[[ endforeach ]]</div></section>
SCORE;
    }

    private function architectureMapScore(): string
    {
        return <<<'SCORE'
<section class="op-shell section-block"><p class="kicker">{{ page.kicker }}</p><h2>{{ page.title }}</h2><div class="layer-grid">[[ foreach: layers as layer ]]<article class="card layer"><span>{{ layer.name }}</span><h3>{{ layer.title }}</h3><p>{{ layer.text }}</p></article>[[ endforeach ]]</div></section>
SCORE;
    }

    private function catalogGridScore(): string
    {
        return <<<'SCORE'
<section class="op-shell section-block"><p class="kicker">{{ page.kicker }}</p><h2>{{ page.title }}</h2><div class="card-grid">[[ foreach: catalog.items as item ]]<article class="card"><span>{{ item.badge }}</span><h3>{{ item.title }}</h3><p>{{ item.text }}</p><a href="{{ item.href }}?lang={{ runtime.lang }}">{{ labels.open }}</a></article>[[ endforeach ]]</div></section>
SCORE;
    }

    private function catalogDetailScore(): string
    {
        return <<<'SCORE'
<section class="op-shell section-block"><p class="kicker">{{ page.kicker }}</p><h2>{{ detail.title }}</h2><p class="wide-text">{{ detail.text }}</p><div class="fact-list">[[ foreach: detail.facts as fact ]]<div><strong>{{ fact.label }}</strong><span>{{ fact.value }}</span></div>[[ endforeach ]]</div></section>
SCORE;
    }

    private function componentLibraryScore(): string
    {
        return <<<'SCORE'
<section class="op-shell section-block"><p class="kicker">{{ page.kicker }}</p><h2>{{ page.title }}</h2><div class="card-grid">[[ foreach: componentCards as component ]]<article class="card"><span>OPUS Component</span><h3>{{ component.name }}</h3><p>{{ component.text }}</p></article>[[ endforeach ]]</div></section>
SCORE;
    }

    private function securityPipelineScore(): string
    {
        return <<<'SCORE'
<section class="op-shell section-block"><p class="kicker">{{ page.kicker }}</p><h2>{{ page.title }}</h2><ol class="pipeline">[[ foreach: securitySteps as item ]]<li><strong>{{ item.step }}</strong><span>{{ item.text }}</span></li>[[ endforeach ]]</ol></section>
SCORE;
    }

    private function backofficePanelScore(): string
    {
        return <<<'SCORE'
<section class="op-shell section-block"><p class="kicker">{{ page.kicker }}</p><h2>{{ page.title }}</h2><div class="card-grid">[[ foreach: backofficeCards as card ]]<article class="card"><h3>{{ card.title }}</h3><p>{{ card.text }}</p></article>[[ endforeach ]]</div></section>
SCORE;
    }

    private function docsPanelScore(): string
    {
        return <<<'SCORE'
<section class="op-shell section-block"><p class="kicker">{{ page.kicker }}</p><h2>{{ page.title }}</h2><div class="card-grid">[[ foreach: docsCards as card ]]<article class="card"><h3>{{ card.title }}</h3><p>{{ card.text }}</p></article>[[ endforeach ]]</div></section>
SCORE;
    }

    private function siteFooterScore(): string
    {
        return '<div class="op-shell footer-line"><span>{{ footer.text }}</span><span>{{ application.id }}</span></div>';
    }

    private function routeProxyContent(int $up): string
    {
        return "<?php\nrequire __DIR__ . '/" . str_repeat('../', $up) . "index.php';\n";
    }

    private function frontControllerContent(): string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);

use Opus\Template\ScoreTemplateRenderer;

$applicationRoot = dirname(__DIR__);
$opusRoot = dirname(dirname(dirname(__DIR__)));
$autoload = $opusRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!is_file($autoload)) { http_response_code(500); echo 'OPUS_AUTOLOAD_MISSING'; exit; }
require $autoload;

function opus_load_json(string $path): array
{
    $decoded = json_decode((string)file_get_contents($path), true);
    if (!is_array($decoded)) { throw new RuntimeException('OPUS_JSON_INVALID: ' . $path); }
    return $decoded;
}

function opus_localize(mixed $value, array $messages): mixed
{
    if (is_array($value)) {
        $localized = [];
        foreach ($value as $key => $item) { $localized[$key] = opus_localize($item, $messages); }
        return $localized;
    }
    if (is_string($value) && str_starts_with($value, '@')) {
        $key = substr($value, 1);
        if (!array_key_exists($key, $messages)) { throw new RuntimeException('OPUS_I18N_KEY_MISSING: ' . $key); }
        return $messages[$key];
    }
    return $value;
}

$lang = (string)($_GET['lang'] ?? 'fr');
if (!in_array($lang, ['fr', 'en', 'es'], true)) { http_response_code(400); echo 'OPUS_LANGUAGE_UNSUPPORTED'; exit; }
$messages = opus_load_json($applicationRoot . '/resources/i18n/' . $lang . '.json');
$path = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$path = rtrim($path, '/');
if ($path === '') { $path = '/'; }

$catalog = opus_localize(opus_load_json($applicationRoot . '/backend/services/catalog/catalog.items.json'), $messages);

if ($path === '/api/catalog') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['contract' => 'OPUS_API_RESPONSE_V1', 'source' => 'backend/services/catalog', 'items' => $catalog['items']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$routes = opus_load_json($applicationRoot . '/middle/routes/routes.json');
$route = $routes['routes'][$path] ?? null;
if (!is_array($route)) { http_response_code(404); echo 'OPUS_ROUTE_NOT_FOUND: ' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8'); exit; }

$viewId = (string)$route['view'];
$view = opus_load_json($applicationRoot . '/frontend/views/' . $viewId . '/' . $viewId . '.view.json');
$viewModel = opus_load_json($applicationRoot . '/backend/viewmodels/' . $viewId . '.viewmodel.json');
$viewModel['catalog'] = $viewModel['catalog'] ?? [];
$viewModel['catalog']['items'] = $catalog['items'];
$viewModel['navigation'] = opus_load_json($applicationRoot . '/frontend/navigation/main.navigation.json');
$viewModel['page'] = ['title' => '@page.' . $viewId . '.title', 'kicker' => '@page.' . $viewId . '.kicker'];
$viewModel = opus_localize($viewModel, $messages);
$viewModel['runtime'] = ['lang' => $lang, 'currentPath' => $path, 'languages' => [['id' => 'fr', 'label' => 'Français'], ['id' => 'en', 'label' => 'English'], ['id' => 'es', 'label' => 'Español']]];

$sectionRenderer = new ScoreTemplateRenderer($applicationRoot . '/frontend/sections');
$slots = [];
foreach (($view['sections'] ?? []) as $section) {
    if (!is_array($section)) { continue; }
    $slot = (string)($section['slot'] ?? '');
    $sectionId = (string)($section['section'] ?? '');
    if ($slot === '' || $sectionId === '') { continue; }
    $slots[$slot] = ($slots[$slot] ?? '') . $sectionRenderer->render($sectionId . '/' . $sectionId . '.section.score', $viewModel);
}

$layout = (string)($view['layout'] ?? 'public');
$viewModel['slots'] = $slots;
$layoutRenderer = new ScoreTemplateRenderer($applicationRoot . '/frontend/layouts');
echo $layoutRenderer->render($layout . '/' . $layout . '.layout.score', $viewModel);
PHP;
    }

    private function readmeContent(): string
    {
        return "# {$this->applicationId}\n\nRich fullstack OPUS application generated by `composer opus:create-application`.\n\n- `frontend/` represents data.\n- `middle/` routes and secures transport.\n- `backend/` processes business/data.\n- OPUS owns standard components.\n- Backoffice is a frontend specialization, not the backend.\n";
    }

    private function startHereContent(): string
    {
        return "# Start here\n\nThis application is a rich fullstack OPUS starter.\n\n## Edit map\n\n- Views: `frontend/views/`\n- Layouts: `frontend/layouts/`\n- Sections: `frontend/sections/`\n- App-specific UI only: `frontend/custom-components/`\n- Routing and security transport: `middle/`\n- Business logic: `backend/services/`\n- Operations: `backend/actions/`\n- Data access: `backend/repositories/`\n- API boundary: `backend/api-endpoints/` and `middle/api/`\n\nNo business logic in frontend representation. No HTML representation in backend services/actions/repositories/validators/policies.\n";
    }

    private function middleContractsReadme(): string
    {
        return "# Middle contracts\n\nThe middle layer owns route matching, request/response transport, API contracts, security gates, ACL/SSO placeholders, FSM gates, rate limiting and audit boundaries.\n";
    }

    private function architectureDoc(): string
    {
        return "# Rich fullstack OPUS application architecture\n\nThis generated application follows `OPUS_FULLSTACK_APPLICATION_V1`.\n\n- `frontend/` represents data with views, layouts, sections and components.\n- `middle/` routes and secures request/response transport.\n- `backend/` processes business/data through services, actions and repositories.\n- The Catalog page demonstrates a backend service consumed by several frontend views and by `/api/catalog`.\n- OPUS owns standard components such as Form, Input and Menu.\n- Backoffice is a frontend specialization, not the backend.\n";
    }

    /** @return array<string,string> */
    private function i18nFr(): array
    {
        return $this->i18n('fr');
    }

    /** @return array<string,string> */
    private function i18nEn(): array
    {
        return $this->i18n('en');
    }

    /** @return array<string,string> */
    private function i18nEs(): array
    {
        return $this->i18n('es');
    }

    /** @return array<string,string> */
    private function i18n(string $lang): array
    {
        $data = [
            'fr' => [
                'app.name' => 'OPUS Fullstack Starter', 'app.subtitle' => 'Frontend, Middle et Backend séparés', 'label.language' => 'LANGUE', 'label.open' => 'Ouvrir', 'label.read_more' => 'Lire la suite', 'label.request_response' => 'Request/Response sécurisées', 'footer.text' => 'Site OPUS généré — secure by design, clean by design',
                'nav.home' => 'Accueil', 'nav.architecture' => 'Architecture', 'nav.catalog' => 'Catalogue', 'nav.components' => 'Composants', 'nav.security' => 'API & Sécurité', 'nav.backoffice' => 'Backoffice', 'nav.docs' => 'Documentation',
                'page.home.title' => 'Vue d’ensemble', 'page.home.kicker' => 'Structure générée', 'page.architecture.title' => 'Architecture Front / Middle / Back', 'page.architecture.kicker' => 'Contrat OPUS', 'page.catalog-index.title' => 'Page Catalogue actif', 'page.catalog-index.kicker' => 'Page backend démonstratif', 'page.catalog-detail.title' => 'Détail du page Catalogue', 'page.catalog-detail.kicker' => 'Backend service', 'page.components.title' => 'Bibliothèque de composants OPUS', 'page.components.kicker' => 'Composants standards', 'page.security.title' => 'Pipeline de sécurité Middle', 'page.security.kicker' => 'Transport sécurisé', 'page.backoffice.title' => 'Backoffice = frontend spécialisé', 'page.backoffice.kicker' => 'Clarification métier', 'page.documentation.title' => 'Contrats générés', 'page.documentation.kicker' => 'Documentation locale',
                'home.kicker' => 'Starter riche', 'home.title' => 'Une application OPUS fullstack, propre et testable', 'home.subtitle' => 'Un frontend riche, un middle sécurisé, un backend métier, des langues et un page Catalogue réel.', 'home.primary' => 'Voir l’architecture', 'home.secondary' => 'Explorer le page',
                'home.card.front.title' => 'Frontend pur', 'home.card.front.text' => 'Views, layouts, sections et composants représentent les données sans logique métier.', 'home.card.middle.title' => 'Middle sécurisé', 'home.card.middle.text' => 'Routage, API, ACL, SSO placeholder, FSM gate, audit et contrats request/response.', 'home.card.back.title' => 'Backend métier', 'home.card.back.text' => 'Services, actions, repositories, validators, policies, runners et jobs.', 'home.card.catalog.title' => 'Page Catalogue', 'home.card.catalog.text' => 'Un service backend alimente plusieurs vues et une API de test.',
                'architecture.kicker' => 'Architecture OPUS', 'architecture.title' => 'Front, Middle et Back sont séparés', 'architecture.subtitle' => 'OPUS oblige le développement clean by design et secure by design.', 'architecture.primary' => 'Voir la sécurité', 'architecture.secondary' => 'Voir les composants', 'front.title' => 'Représentation', 'front.text' => 'Le Front rend les views avec layouts, sections et composants.', 'middle.title' => 'Transport sécurisé', 'middle.text' => 'Le Middle route, valide les contrats, contrôle l’accès et trace.', 'back.title' => 'Traitement métier', 'back.text' => 'Le Back exécute les actions métier et accède aux données.',
                'catalog.kicker' => 'Catalogue backend', 'catalog.title' => 'Une page, plusieurs vues, une API', 'catalog.subtitle' => 'Le page Catalogue est exposé via le Middle et représenté par le Front.', 'catalog.primary' => 'Ouvrir le détail', 'catalog.secondary' => 'Voir JSON API', 'catalog.detail.kicker' => 'Détail page', 'catalog.detail.title' => 'Page Catalogue', 'catalog.detail.subtitle' => 'Un service backend non-CMS, non-blog, consommé par des views.', 'catalog.detail.primary' => 'Retour Catalogue', 'catalog.detail.secondary' => 'Tester API', 'catalog.page.title' => 'Page Catalogue', 'catalog.page.text' => 'Données métier structurées pour démontrer le backend.', 'catalog.page.detail' => 'Le page Catalogue est traité côté backend, transporté par le middle et rendu côté frontend sans mélange des responsabilités.', 'catalog.security.title' => 'Pipeline sécurité', 'catalog.security.text' => 'Transport request/response sécurisé avant action backend.', 'catalog.components.title' => 'Composants standards', 'catalog.components.text' => 'Menus, forms, inputs et cards appartiennent au framework OPUS.',
                'badge.backend' => 'Backend', 'badge.middle' => 'Middle', 'badge.front' => 'Front', 'fact.backend' => 'Page backend', 'fact.api' => 'Endpoint API', 'fact.views' => 'Views consommatrices',
                'components.kicker' => 'Composants OPUS', 'components.title' => 'Les composants standards appartiennent à OPUS', 'components.subtitle' => 'L’application ne contient que ses composants custom éventuels.', 'components.primary' => 'Voir le catalogue', 'components.secondary' => 'Voir l’architecture', 'component.menu' => 'Navigation représentée par un composant standard.', 'component.form' => 'Form est un composant spécialisé placé dans une section.', 'component.input' => 'Input est un sous-composant de Form.', 'component.card' => 'Card représente une donnée déjà préparée.', 'component.table' => 'Table affiche un ViewModel, jamais une requête métier directe.',
                'security.kicker' => 'Middle', 'security.title' => 'API, FSM, ACL, SSO et audit avant le backend', 'security.subtitle' => 'Le backend ne reçoit que des requests contractuelles déjà passées par les gates.', 'security.primary' => 'Voir backoffice', 'security.secondary' => 'Voir architecture', 'security.route' => 'La route est résolue dans middle/routes.', 'security.request' => 'La request est normalisée avant traitement.', 'security.acl' => 'ACL et SSO sont des gates de transport.', 'security.fsm' => 'La FSM empêche les actions hors état autorisé.', 'security.audit' => 'Chaque passage peut être tracé.', 'security.action' => 'L’action backend exécute le métier uniquement.',
                'backoffice.kicker' => 'Backoffice', 'backoffice.title' => 'Backoffice n’est pas backend', 'backoffice.subtitle' => 'Le backoffice est un frontend spécialisé qui consomme le backend.', 'backoffice.primary' => 'Voir sécurité', 'backoffice.secondary' => 'Retour accueil', 'backoffice.card.front.title' => 'Frontend backoffice', 'backoffice.card.front.text' => 'Views et composants administratifs.', 'backoffice.card.backend.title' => 'Backend commun', 'backoffice.card.backend.text' => 'Les services métier restent côté backend.', 'backoffice.card.secure.title' => 'Accès contrôlé', 'backoffice.card.secure.text' => 'Le Middle filtre les droits avant action.',
                'docs.kicker' => 'Documentation', 'docs.title' => 'Contrats générés avec l’application', 'docs.subtitle' => 'Les fichiers générés expliquent quoi modifier sans casser la séparation.', 'docs.primary' => 'Voir architecture', 'docs.secondary' => 'Voir catalogue', 'docs.application' => 'Contrat fullstack racine.', 'docs.routes' => 'Routes middle vers les views.', 'docs.catalog' => 'Page backend démonstratif.',
            ],
        ];
        $data['en'] = array_map(static fn (string $value): string => strtr($value, ['Application' => 'Application', 'Frontend' => 'Frontend', 'Backend' => 'Backend']), [
            'app.name' => 'OPUS Fullstack Starter', 'app.subtitle' => 'Separated Front, Middle and Back', 'label.language' => 'LANGUAGE', 'label.open' => 'Open', 'label.read_more' => 'Read more', 'label.request_response' => 'Secured Request/Response', 'footer.text' => 'Generated OPUS site — secure by design, clean by design',
            'nav.home' => 'Home', 'nav.architecture' => 'Architecture', 'nav.catalog' => 'Catalog', 'nav.components' => 'Components', 'nav.security' => 'API & Security', 'nav.backoffice' => 'Backoffice', 'nav.docs' => 'Documentation',
            'page.home.title' => 'Overview', 'page.home.kicker' => 'Generated structure', 'page.architecture.title' => 'Front / Middle / Back architecture', 'page.architecture.kicker' => 'OPUS contract', 'page.catalog-index.title' => 'Active Catalog page', 'page.catalog-index.kicker' => 'Demo backend service', 'page.catalog-detail.title' => 'Catalog page detail', 'page.catalog-detail.kicker' => 'Backend service', 'page.components.title' => 'OPUS component library', 'page.components.kicker' => 'Standard components', 'page.security.title' => 'Middle security pipeline', 'page.security.kicker' => 'Secured transport', 'page.backoffice.title' => 'Backoffice = specialized frontend', 'page.backoffice.kicker' => 'Business clarification', 'page.documentation.title' => 'Generated contracts', 'page.documentation.kicker' => 'Local documentation',
            'home.kicker' => 'Rich starter', 'home.title' => 'A clean and testable OPUS fullstack application', 'home.subtitle' => 'A rich frontend, a secured middle, a business backend, languages and a real Catalog page.', 'home.primary' => 'View architecture', 'home.secondary' => 'Explore page',
            'home.card.front.title' => 'Pure frontend', 'home.card.front.text' => 'Views, layouts, sections and components represent data without business logic.', 'home.card.middle.title' => 'Secured middle', 'home.card.middle.text' => 'Routing, API, ACL, SSO placeholder, FSM gate, audit and request/response contracts.', 'home.card.back.title' => 'Business backend', 'home.card.back.text' => 'Services, actions, repositories, validators, policies, runners and jobs.', 'home.card.catalog.title' => 'Catalog page', 'home.card.catalog.text' => 'A backend service feeds several views and a test API.',
            'architecture.kicker' => 'OPUS architecture', 'architecture.title' => 'Front, Middle and Back are separated', 'architecture.subtitle' => 'OPUS enforces clean by design and secure by design development.', 'architecture.primary' => 'View security', 'architecture.secondary' => 'View components', 'front.title' => 'Representation', 'front.text' => 'Front renders views with layouts, sections and components.', 'middle.title' => 'Secured transport', 'middle.text' => 'Middle routes, validates contracts, controls access and audits.', 'back.title' => 'Business processing', 'back.text' => 'Back executes business actions and accesses data.',
            'catalog.kicker' => 'Backend catalog', 'catalog.title' => 'One page, several views, one API', 'catalog.subtitle' => 'The Catalog page is exposed through Middle and represented by Front.', 'catalog.primary' => 'Open detail', 'catalog.secondary' => 'View JSON API', 'catalog.detail.kicker' => 'Page detail', 'catalog.detail.title' => 'Catalog page', 'catalog.detail.subtitle' => 'A non-CMS, non-blog backend service consumed by views.', 'catalog.detail.primary' => 'Back to Catalog', 'catalog.detail.secondary' => 'Test API', 'catalog.page.title' => 'Catalog page', 'catalog.page.text' => 'Structured business data demonstrating the backend.', 'catalog.page.detail' => 'The Catalog page is processed in backend, transported by middle and rendered in frontend without mixing responsibilities.', 'catalog.security.title' => 'Security pipeline', 'catalog.security.text' => 'Secured request/response transport before backend action.', 'catalog.components.title' => 'Standard components', 'catalog.components.text' => 'Menus, forms, inputs and cards belong to the OPUS framework.',
            'badge.backend' => 'Backend', 'badge.middle' => 'Middle', 'badge.front' => 'Front', 'fact.backend' => 'Backend service', 'fact.api' => 'API endpoint', 'fact.views' => 'Consuming views',
            'components.kicker' => 'OPUS components', 'components.title' => 'Standard components belong to OPUS', 'components.subtitle' => 'The application only contains possible custom components.', 'components.primary' => 'View catalog', 'components.secondary' => 'View architecture', 'component.menu' => 'Navigation represented by a standard component.', 'component.form' => 'Form is a specialized component placed in a section.', 'component.input' => 'Input is a Form subcomponent.', 'component.card' => 'Card represents already prepared data.', 'component.table' => 'Table displays a ViewModel, never a direct business query.',
            'security.kicker' => 'Middle', 'security.title' => 'API, FSM, ACL, SSO and audit before backend', 'security.subtitle' => 'Backend receives only contractual requests that passed gates.', 'security.primary' => 'View backoffice', 'security.secondary' => 'View architecture', 'security.route' => 'Route is resolved in middle/routes.', 'security.request' => 'Request is normalized before processing.', 'security.acl' => 'ACL and SSO are transport gates.', 'security.fsm' => 'FSM blocks actions outside authorized state.', 'security.audit' => 'Every passage can be audited.', 'security.action' => 'Backend action executes business only.',
            'backoffice.kicker' => 'Backoffice', 'backoffice.title' => 'Backoffice is not backend', 'backoffice.subtitle' => 'Backoffice is a specialized frontend consuming the backend.', 'backoffice.primary' => 'View security', 'backoffice.secondary' => 'Back home', 'backoffice.card.front.title' => 'Backoffice frontend', 'backoffice.card.front.text' => 'Administrative views and components.', 'backoffice.card.backend.title' => 'Shared backend', 'backoffice.card.backend.text' => 'Business services remain in backend.', 'backoffice.card.secure.title' => 'Controlled access', 'backoffice.card.secure.text' => 'Middle filters rights before action.',
            'docs.kicker' => 'Documentation', 'docs.title' => 'Contracts generated with the application', 'docs.subtitle' => 'Generated files explain what to edit without breaking separation.', 'docs.primary' => 'View architecture', 'docs.secondary' => 'View catalog', 'docs.application' => 'Root fullstack contract.', 'docs.routes' => 'Middle routes to views.', 'docs.catalog' => 'Demonstration backend service.',
        ]);
        $data['es'] = $data['fr'];
        $data['es']['app.subtitle'] = 'Front, Middle y Back separados';
        $data['es']['label.language'] = 'IDIOMA';
        $data['es']['nav.home'] = 'Inicio';
        $data['es']['nav.architecture'] = 'Arquitectura';
        $data['es']['nav.catalog'] = 'Catálogo';
        $data['es']['nav.components'] = 'Componentes';
        $data['es']['nav.security'] = 'API y Seguridad';
        $data['es']['nav.backoffice'] = 'Backoffice';
        $data['es']['nav.docs'] = 'Documentación';
        $data['es']['home.title'] = 'Una aplicación OPUS fullstack limpia y comprobable';
        $data['es']['home.subtitle'] = 'Un frontend rico, un middle seguro, un backend de negocio, idiomas y un módulo Catálogo real.';
        $data['es']['catalog.page.title'] = 'Módulo Catálogo';
        return $data[$lang];
    }

    private function applicationCss(): string
    {
        return <<<'CSS'
:root{--bg:#07111f;--panel:#0d1a2d;--line:#29405f;--text:#f6f8ff;--muted:#b6c5dc;--accent:#58b7ff;--accent2:#69e3ff;--soft:#14243b}*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 70% 0,#19385e 0,#07111f 34rem);color:var(--text);font-family:Segoe UI,Arial,sans-serif}.op-shell{width:min(1180px,calc(100% - 48px));margin:0 auto}.op-topbar{height:74px;border-bottom:1px solid var(--line);display:flex;gap:20px;align-items:center;justify-content:center;background:rgba(5,13,26,.86);position:sticky;top:0;z-index:10}.brand{display:grid;grid-template-columns:42px auto;grid-template-rows:auto auto;gap:0 12px;color:var(--text);text-decoration:none;min-width:250px}.brand span{grid-row:1/3;width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#3f8cff,#70e3ff);display:grid;place-items:center;font-weight:900}.brand strong{font-size:1rem}.brand small{color:var(--muted)}.main-nav{display:flex;gap:8px;flex-wrap:wrap}.main-nav a,.language-switcher a,.actions a,.card a{border:1px solid rgba(148,170,216,.28);border-radius:999px;color:var(--text);padding:9px 14px;text-decoration:none;font-weight:700}.main-nav a:hover,.language-switcher a:hover,.actions a:hover,.card a:hover{border-color:var(--accent);color:#dff5ff}.language-switcher{display:flex;align-items:center;gap:8px}.language-switcher b{font-size:.78rem;color:var(--muted);letter-spacing:.08em}.hero{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:28px;padding:64px 0 38px}.hero>div,.contract-card,.card{background:rgba(13,26,45,.86);border:1px solid var(--line);border-radius:24px;box-shadow:0 22px 80px rgba(0,0,0,.22)}.hero>div{padding:52px}.kicker{color:var(--accent2);font-weight:900;letter-spacing:.13em;text-transform:uppercase;font-size:.8rem}.hero h1{font-size:clamp(2.3rem,5vw,4.8rem);line-height:.95;margin:.2em 0}.lead{font-size:1.25rem;line-height:1.5;color:#d8e7fb;max-width:780px}.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:28px}.actions .primary{background:#12375c;border-color:#48b7ff}.contract-card{padding:32px;display:flex;flex-direction:column;gap:16px}.contract-card p{color:var(--accent2);font-weight:900;letter-spacing:.12em}.contract-card strong{font-size:1.45rem}.contract-card span{color:var(--muted)}.section-block{padding:30px 0 70px}.section-block h2{font-size:clamp(1.9rem,4vw,3rem);margin:.2em 0 .6em}.card-grid,.layer-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}.layer-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.card{padding:24px;min-height:185px}.card span,.layer span{display:inline-block;color:var(--accent2);font-weight:900;letter-spacing:.12em;text-transform:uppercase;font-size:.72rem;margin-bottom:12px}.card h3{font-size:1.28rem;margin:.2em 0}.card p,.wide-text,.pipeline span,.fact-list span{color:#cbd9ee;line-height:1.45}.fact-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-top:24px}.fact-list div,.pipeline li{background:var(--soft);border:1px solid var(--line);border-radius:16px;padding:18px}.pipeline{list-style:none;padding:0;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.pipeline strong{display:block;color:var(--accent2);margin-bottom:8px}.footer-line{display:flex;justify-content:space-between;gap:16px;border-top:1px solid var(--line);padding:22px 0;color:var(--muted)}.opus-backoffice{background:radial-gradient(circle at 70% 0,#35235f 0,#07111f 34rem)}@media(max-width:980px){.op-topbar{height:auto;align-items:flex-start;flex-direction:column;padding:16px 24px}.hero,.card-grid,.layer-grid,.pipeline,.fact-list{grid-template-columns:1fr}.hero>div{padding:32px}}
CSS;
    }
}
