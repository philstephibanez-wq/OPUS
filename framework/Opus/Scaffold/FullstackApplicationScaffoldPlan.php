<?php
declare(strict_types=1);

namespace Opus\Scaffold;

/** Fullstack OPUS application scaffold plan. */
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
            'frontend/views/home', 'frontend/layouts/public',
            'frontend/sections/site-header', 'frontend/sections/home-hero', 'frontend/sections/home-main', 'frontend/sections/site-footer',
            'frontend/custom-components', 'frontend/navigation', 'frontend/api-clients', 'frontend/assets/css', 'frontend/assets/js', 'frontend/theme',
            'backend/modules/content', 'backend/modules/navigation', 'backend/services', 'backend/actions', 'backend/repositories',
            'backend/validators', 'backend/policies', 'backend/api-endpoints', 'backend/runners', 'backend/jobs', 'backend/dto', 'backend/viewmodels',
            'resources/i18n', 'resources/data', 'docs',
        ];
        $entries = array_map(static fn (string $directory): ScaffoldEntry => ScaffoldEntry::directory("sites/{$app}/{$directory}"), $directories);

        $entries[] = ScaffoldEntry::file("sites/{$app}/README.md", $this->readmeContent());
        $entries[] = ScaffoldEntry::file("sites/{$app}/START_HERE.md", $this->startHereContent());
        $entries[] = ScaffoldEntry::file("sites/{$app}/application.opus.json", $this->json($this->applicationContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/views/home/home.view.json", $this->json($this->homeViewContract()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/layouts/public/public.layout.json", $this->json(['contract' => 'OPUS_FRONTEND_LAYOUT_V1', 'id' => 'public', 'template' => 'public.layout.score', 'slots' => ['header', 'hero', 'main', 'footer'], 'nested_layouts_allowed' => true]));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/layouts/public/public.layout.score", '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ application.name }}</title><link rel="stylesheet" href="/assets/css/application.css"></head><body class="opus-fullstack-application"><header>{{{ slots.header }}}</header><main><section>{{{ slots.hero }}}</section><section id="contract">{{{ slots.main }}}</section></main><footer>{{{ slots.footer }}}</footer></body></html>');
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/sections/site-header/site-header.section.json", $this->json(['contract' => 'OPUS_FRONTEND_SECTION_V1', 'id' => 'site-header', 'components' => [['component' => 'TextBlock'], ['component' => 'Menu']]]));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/sections/site-header/site-header.section.score", '<div class="opus-shell opus-header"><strong>{{ application.name }}</strong><nav>[[ foreach: navigation.items as item ]]<a href="{{ item.href }}">{{ item.label }}</a>[[ endforeach ]]</nav></div>');
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/sections/home-hero/home-hero.section.json", $this->json(['contract' => 'OPUS_FRONTEND_SECTION_V1', 'id' => 'home-hero', 'components' => [['component' => 'TextBlock'], ['component' => 'Button']]]));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/sections/home-hero/home-hero.section.score", '<div class="opus-shell opus-hero"><p>{{ hero.kicker }}</p><h1>{{ hero.title }}</h1><p>{{ hero.subtitle }}</p><a class="opus-button" href="{{ hero.primaryAction.href }}">{{ hero.primaryAction.label }}</a></div>');
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/sections/home-main/home-main.section.json", $this->json(['contract' => 'OPUS_FRONTEND_SECTION_V1', 'id' => 'home-main', 'components' => [['component' => 'Card']]]));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/sections/home-main/home-main.section.score", '<div class="opus-shell opus-grid">[[ foreach: contractCards as card ]]<article><h2>{{ card.title }}</h2><p>{{ card.text }}</p></article>[[ endforeach ]]</div>');
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/sections/site-footer/site-footer.section.json", $this->json(['contract' => 'OPUS_FRONTEND_SECTION_V1', 'id' => 'site-footer', 'components' => [['component' => 'TextBlock']]]));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/sections/site-footer/site-footer.section.score", '<div class="opus-shell opus-footer"><span>{{ footer.text }}</span></div>');
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/navigation/main.navigation.json", $this->json(['contract' => 'OPUS_FRONTEND_NAVIGATION_V1', 'component' => 'OPUS.StandardComponent.Menu', 'items' => [['label' => 'Accueil', 'href' => '/', 'order' => 10], ['label' => 'Contrat', 'href' => '#contract', 'order' => 20]]]));
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/api-clients/README.md", "# API clients\n\nFrontend API clients call backend API endpoints and translate request/response DTOs for views and components.\n\nThey must not contain business logic.\n");
        $entries[] = ScaffoldEntry::file("sites/{$app}/frontend/custom-components/README.md", "# Custom components\n\nStandard components belong to OPUS. Place only application-specific components here.\n");
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/modules/content/module.opus.json", $this->json(['contract' => 'OPUS_BACKEND_MODULE_V1', 'id' => 'content', 'role' => 'business-domain', 'frontend_view' => false]));
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/modules/navigation/module.opus.json", $this->json(['contract' => 'OPUS_BACKEND_MODULE_V1', 'id' => 'navigation', 'role' => 'business-domain', 'frontend_view' => false]));
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/api-endpoints/home-viewmodel.endpoint.json", $this->json(['contract' => 'OPUS_BACKEND_API_ENDPOINT_V1', 'id' => 'home.viewmodel', 'method' => 'GET', 'path' => '/api/viewmodels/home', 'action' => 'BuildHomeViewModelAction', 'response' => 'HomeViewModel']));
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/actions/BuildHomeViewModelAction.md", "# BuildHomeViewModelAction\n\nBackend action placeholder. It builds representation-ready data for the frontend home view by calling backend services. It must not render HTML.\n");
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/services/ContentService.md", "# ContentService\n\nBackend service placeholder. It processes content-oriented data and must not know frontend layout details.\n");
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/viewmodels/home.viewmodel.json", $this->json($this->homeViewModel()));
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/runners/README.md", "# Runners\n\nBackend runners execute backend processing without a frontend request.\n");
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/jobs/README.md", "# Jobs\n\nBackend jobs contain asynchronous/background processing definitions.\n");
        $entries[] = ScaffoldEntry::file("sites/{$app}/backend/dto/README.md", "# DTO\n\nDTO files define request/response contracts crossing the frontend/backend boundary.\n");
        $entries[] = ScaffoldEntry::file("sites/{$app}/resources/i18n/fr.json", $this->json(['application.fullstack' => 'Application OPUS fullstack', 'frontend.role' => 'Représentation', 'backend.role' => 'Traitement métier et données']));
        $entries[] = ScaffoldEntry::file("sites/{$app}/public/assets/css/application.css", $this->applicationCss());
        $entries[] = ScaffoldEntry::file("sites/{$app}/public/index.php", $this->frontControllerContent());
        $entries[] = ScaffoldEntry::file("sites/{$app}/docs/architecture.md", "# Fullstack OPUS application architecture\n\nThis generated application follows `OPUS_FULLSTACK_APPLICATION_V1`.\n\n- `frontend/` represents data.\n- `backend/` processes business/data.\n- API request/response contracts separate the two.\n- OPUS owns standard components such as Form, Input and Menu.\n- Backoffice is a frontend specialization, not the backend.\n");
        return $entries;
    }

    /** @return array<string,mixed> */
    private function applicationContract(): array
    {
        return ['application_id' => $this->applicationId, 'type' => 'opus-fullstack-application', 'contract' => 'OPUS_FULLSTACK_APPLICATION_V1', 'frontend_contract' => 'OPUS_FRONTEND_VIEWS_LAYOUTS_SECTIONS_COMPONENTS_V1', 'backend_contract' => 'OPUS_BACKEND_BUSINESS_DATA_PROCESSING_V1', 'standard_components_owner' => 'OPUS', 'custom_components_owner' => 'application', 'created_by' => 'composer opus:create-application', 'external_dependencies_allowed' => false, 'framework_duplication_allowed' => false, 'backoffice_is_backend' => false, 'frontend_root' => 'frontend', 'backend_root' => 'backend', 'public_root' => 'public', 'resources_root' => 'resources'];
    }

    /** @return array<string,mixed> */
    private function homeViewContract(): array
    {
        return ['contract' => 'OPUS_FRONTEND_VIEW_V1', 'id' => 'home', 'route' => '/', 'layout' => 'public', 'viewmodel' => 'home', 'sections' => [['slot' => 'header', 'section' => 'site-header'], ['slot' => 'hero', 'section' => 'home-hero'], ['slot' => 'main', 'section' => 'home-main'], ['slot' => 'footer', 'section' => 'site-footer']]];
    }

    /** @param array<string,mixed> $data */
    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function readmeContent(): string
    {
        return "# {$this->applicationId}\n\nFullstack OPUS application generated by `composer opus:create-application`.\n\n- `frontend/` represents data.\n- `backend/` processes business/data.\n- OPUS owns standard components.\n- Backoffice is a frontend specialization, not the backend.\n";
    }

    private function startHereContent(): string
    {
        return "# Start here\n\nThis application is a fullstack OPUS application.\n\n## Edit map\n\n- Add a view: `frontend/views/`\n- Change visual structure: `frontend/layouts/`\n- Add a section: `frontend/sections/`\n- Add custom UI only: `frontend/custom-components/`\n- Add business logic: `backend/services/`\n- Add an operation: `backend/actions/`\n- Add API entry point: `backend/api-endpoints/`\n\nNo business logic in frontend representation. No HTML representation in backend services/actions/repositories/validators/policies.\n";
    }

    /** @return array<string,mixed> */
    private function homeViewModel(): array
    {
        return ['contract' => 'OPUS_VIEWMODEL_V1', 'application' => ['id' => $this->applicationId, 'name' => 'Application OPUS ' . $this->applicationId], 'navigation' => ['items' => [['label' => 'Accueil', 'href' => '/'], ['label' => 'Contrat', 'href' => '#contract']]], 'hero' => ['kicker' => 'Fullstack OPUS', 'title' => 'Frontend et backend clairement séparés', 'subtitle' => 'Le frontend représente les données. Le backend traite les données et le métier.', 'primaryAction' => ['label' => 'Lire le contrat', 'href' => '#contract']], 'contractCards' => [['title' => 'Frontend', 'text' => 'Views, layouts, sections, composants, navigation, API clients, assets et thème.'], ['title' => 'Backend', 'text' => 'Modules métier, services, actions, repositories, validators, policies, API endpoints, runners, jobs, DTO et viewmodels.'], ['title' => 'Composants OPUS', 'text' => 'Form, input, menu, card, table et autres composants standards appartiennent à OPUS.'], ['title' => 'Backoffice', 'text' => 'Un backoffice est un frontend spécialisé. Ce n’est jamais le backend.']], 'footer' => ['text' => 'Generated by composer opus:create-application — OPUS_FULLSTACK_APPLICATION_V1']];
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
$view = json_decode((string)file_get_contents($applicationRoot . '/frontend/views/home/home.view.json'), true);
$viewModel = json_decode((string)file_get_contents($applicationRoot . '/backend/viewmodels/home.viewmodel.json'), true);
if (!is_array($view) || !is_array($viewModel)) { http_response_code(500); echo 'OPUS_FULLSTACK_VIEW_CONTRACT_INVALID'; exit; }
$sectionRenderer = new ScoreTemplateRenderer($applicationRoot . '/frontend/sections');
$slots = [];
foreach (($view['sections'] ?? []) as $section) {
    if (!is_array($section)) { continue; }
    $slot = (string)($section['slot'] ?? '');
    $sectionId = (string)($section['section'] ?? '');
    if ($slot === '' || $sectionId === '') { continue; }
    $slots[$slot] = ($slots[$slot] ?? '') . $sectionRenderer->render($sectionId . '/' . $sectionId . '.section.score', $viewModel);
}
$layoutRenderer = new ScoreTemplateRenderer($applicationRoot . '/frontend/layouts');
echo $layoutRenderer->render('public/public.layout.score', array_merge($viewModel, ['slots' => $slots]));
PHP;
    }

    private function applicationCss(): string
    {
        return 'body{margin:0;background:#07111f;color:#f6f8ff;font-family:Segoe UI,Arial,sans-serif}.opus-shell{width:min(1120px,calc(100% - 48px));margin:0 auto}.opus-header,.opus-footer{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 0}.opus-header nav{display:flex;gap:10px}.opus-header a,.opus-button{color:#f6f8ff;text-decoration:none;border:1px solid rgba(148,170,216,.25);border-radius:999px;padding:8px 12px}.opus-hero{padding:72px 0}.opus-hero h1{font-size:clamp(2.3rem,5vw,5rem);line-height:.95;margin:.2em 0}.opus-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;padding-bottom:72px}.opus-grid article{background:rgba(16,28,47,.88);border:1px solid rgba(148,170,216,.25);border-radius:24px;padding:24px}@media(max-width:760px){.opus-grid{grid-template-columns:1fr}}';
    }
}
