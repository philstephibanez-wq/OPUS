<?php
declare(strict_types=1);

namespace Opus\Owasys;

use RuntimeException;

/**
 * Writes a validated OWASYS scaffold plan to disk.
 *
 * OWASYS now treats the application state as the canonical dispatch node. The
 * old controller name remains only as a legacy alias for the application tree.
 */
final class ApplicationScaffoldWriter
{
    private const SITE_CONTRACT = 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL';
    private const APPLICATION_FSM_CONTRACT = 'OPUS_APPLICATION_FSM_V1';
    private const LEGACY_FSM_CONTRACT = 'OPUS_FSM_REGISTRY_V1';
    private const FORBIDDEN_SEGMENTS = ['..', 'public', 'src', 'resources'];

    public function __construct(private readonly string $opusRoot)
    {
    }

    /**
     * Writes or previews a scaffold plan.
     *
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    public function write(array $plan, bool $dryRun = true): array
    {
        $normalized = $this->normalizePlan($plan);
        $siteRoot = $normalized['site_root'];
        $siteRootAbsolute = $this->absolutePath($siteRoot);

        if (!$dryRun && file_exists($siteRootAbsolute)) {
            throw new RuntimeException('OWASYS_SCAFFOLD_TARGET_ALREADY_EXISTS: ' . $siteRoot);
        }

        if ($dryRun) {
            return $this->summary($normalized, 'dry-run');
        }

        foreach ($normalized['directories'] as $directory) {
            $absolute = $this->absolutePath($directory);
            if (!is_dir($absolute) && !mkdir($absolute, 0775, true) && !is_dir($absolute)) {
                throw new RuntimeException('OWASYS_SCAFFOLD_DIRECTORY_CREATE_FAILED: ' . $directory);
            }
        }

        foreach ($normalized['files'] as $file) {
            $path = $file['path'];
            $absolute = $this->absolutePath($path);
            if (file_exists($absolute)) {
                throw new RuntimeException('OWASYS_SCAFFOLD_FILE_ALREADY_EXISTS: ' . $path);
            }
            $parent = dirname($absolute);
            if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
                throw new RuntimeException('OWASYS_SCAFFOLD_DIRECTORY_CREATE_FAILED: ' . dirname($path));
            }
            if (file_put_contents($absolute, $this->contentForFile($normalized, $file)) === false) {
                throw new RuntimeException('OWASYS_SCAFFOLD_FILE_WRITE_FAILED: ' . $path);
            }
        }

        return $this->summary($normalized, 'write');
    }

    /**
     * @param array<string,mixed> $plan
     * @return array{site_id:string,slug:string,name:string,kind:string,blueprint:string,site_root:string,default_locale:string,theme:string,controllers:list<string>,routes:list<array<string,mixed>>,datasources:list<array<string,mixed>>,security_profiles:list<array<string,mixed>>,workflows:list<array<string,mixed>>,directories:list<string>,files:list<array{path:string,kind:string,content_source:string}>}
     */
    private function normalizePlan(array $plan): array
    {
        if (($plan['contract'] ?? null) !== self::SITE_CONTRACT) {
            throw new RuntimeException('OWASYS_SCAFFOLD_PLAN_CONTRACT_INVALID');
        }

        $siteId = $this->stringField($plan, 'site_id');
        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $siteId) !== 1) {
            throw new RuntimeException('OWASYS_SCAFFOLD_SITE_ID_INVALID');
        }

        $siteRoot = $this->relativePathField($plan, 'site_root');
        $controllers = $this->stringListField($plan, 'controllers');
        if (!in_array('home', $controllers, true)) {
            throw new RuntimeException('OWASYS_SCAFFOLD_HOME_CONTROLLER_REQUIRED');
        }

        $directories = $this->pathListField($plan, 'directories', $siteRoot);
        $files = $this->fileListField($plan, 'files', $siteRoot);

        foreach (['config', 'application/default', 'www', 'www/index.php', 'www/asset', 'config/application.fsm.json'] as $required) {
            $needle = $siteRoot . '/' . $required;
            if (str_contains($required, '.')) {
                if (!$this->fileExistsInPlan($files, $needle)) {
                    throw new RuntimeException('OWASYS_SCAFFOLD_REQUIRED_FILE_MISSING: ' . $needle);
                }
                continue;
            }
            if (!in_array($needle, $directories, true)) {
                throw new RuntimeException('OWASYS_SCAFFOLD_REQUIRED_DIRECTORY_MISSING: ' . $needle);
            }
        }

        return [
            'site_id' => $siteId,
            'slug' => $this->stringField($plan, 'slug'),
            'name' => $this->stringField($plan, 'name'),
            'kind' => $this->stringField($plan, 'kind'),
            'blueprint' => $this->stringField($plan, 'blueprint'),
            'site_root' => $siteRoot,
            'default_locale' => $this->stringField($plan, 'default_locale'),
            'theme' => $this->stringField($plan, 'theme'),
            'controllers' => $controllers,
            'routes' => $this->arrayListField($plan, 'routes'),
            'datasources' => $this->arrayListField($plan, 'datasources'),
            'security_profiles' => $this->arrayListField($plan, 'security_profiles'),
            'workflows' => $this->arrayListField($plan, 'workflows'),
            'directories' => $directories,
            'files' => $files,
        ];
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private function summary(array $plan, string $mode): array
    {
        return [
            'mode' => $mode,
            'site_id' => $plan['site_id'],
            'site_root' => $plan['site_root'],
            'directories' => count($plan['directories']),
            'files' => count($plan['files']),
        ];
    }

    /** @param array<string,mixed> $plan @param array{path:string,kind:string,content_source:string} $file */
    private function contentForFile(array $plan, array $file): string
    {
        $path = $file['path'];
        $state = $this->stateFromPath($path);

        if (str_ends_with($path, '/config/site.json')) {
            return $this->json($this->siteConfig($plan));
        }
        if (str_ends_with($path, '/config/routes.json')) {
            return $this->json($this->routesConfig($plan));
        }
        if (str_ends_with($path, '/config/menu.json')) {
            return $this->json($this->menuConfig($plan));
        }
        if (str_ends_with($path, '/config/application.fsm.json')) {
            return $this->json($this->applicationFsmConfig($plan));
        }
        if (str_ends_with($path, '/config/fsm.json')) {
            return $this->json($this->legacyFsmConfig($plan));
        }
        if (str_ends_with($path, '/config/rubrics.json')) {
            return $this->json($this->rubricsConfig($plan));
        }
        if (str_ends_with($path, '/application/default/templates/layout.score')) {
            return "<!doctype html>\n<html lang=\"{{ lang }}\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>{{ page.title }}</title>\n{{{ assets.css }}}\n</head>\n<body class=\"opus-generated-site\">\n{{{ common.header }}}\n<main id=\"main-content\" class=\"opus-shell\">{{{ content }}}</main>\n{{{ common.footer }}}\n{{{ assets.js }}}\n</body>\n</html>\n";
        }
        if (str_ends_with($path, '/application/default/templates/components/header.score')) {
            return "<header class=\"opus-header\"><h1>{{ site.name }}</h1><nav>{{{ common.menu }}}</nav></header>\n";
        }
        if (str_ends_with($path, '/application/default/templates/components/footer.score')) {
            return "<footer class=\"opus-footer\">{{ site.contract }}</footer>\n";
        }
        if (str_ends_with($path, '/application/default/css/default.css')) {
            return $this->defaultCss();
        }
        if (str_ends_with($path, '/application/default/javascript/default.js')) {
            return "document.documentElement.dataset.opusDefaultLayer='loaded';\n";
        }
        if (str_ends_with($path, '/www/index.php')) {
            return $this->frontController();
        }
        if (str_ends_with($path, '/theme.css')) {
            return $this->themeCss((string) $plan['theme']);
        }
        if (str_ends_with($path, '/theme.js')) {
            return "document.documentElement.dataset.opusThemeLayer='" . $plan['theme'] . "';\n";
        }
        if ($state !== null && str_ends_with($path, '/templates/index.score')) {
            return "<section class=\"opus-card\"><h2>{{ page.title }}</h2><p>{{ page.summary }}</p></section>\n";
        }
        if ($state !== null && str_ends_with($path, '/views/index.php')) {
            return $this->viewModel($state, $plan);
        }
        if ($state !== null && str_ends_with($path, '/' . $state . '.css')) {
            return "/* " . $state . " */\n";
        }
        if ($state !== null && str_ends_with($path, '/' . $state . '.js')) {
            return "document.documentElement.dataset.opusStateLayer='" . $state . "';\n";
        }
        if (str_ends_with($path, '/i18n.json')) {
            $labels = $this->pageLabels($state ?? 'default', $plan);
            return $this->json(['page.title' => $labels['title'], 'page.summary' => $labels['summary']]);
        }

        return "";
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private function siteConfig(array $plan): array
    {
        return [
            'contract' => self::SITE_CONTRACT,
            'site_id' => $plan['site_id'],
            'site_name' => $plan['name'],
            'role' => 'generated-opus-application',
            'kind' => $plan['kind'],
            'blueprint' => $plan['blueprint'],
            'default_locale' => $plan['default_locale'],
            'locales' => [$plan['default_locale']],
            'theme' => $plan['theme'],
            'public_root' => 'www',
            'application_root' => 'application',
            'default_root' => 'application/default',
            'asset_root' => 'www/asset',
            'theme_root_pattern' => 'www/asset/themes/<theme>',
            'application_fsm' => 'config/application.fsm.json',
            'fsm_legacy_projection' => 'config/fsm.json',
            'fsm_contract' => self::APPLICATION_FSM_CONTRACT,
            'dispatch_model' => 'state-first',
            'controller_field' => 'legacy_alias',
            'src_directory_allowed' => false,
            'css_inheritance' => ['application/default/css', 'www/asset/themes/<theme>/css', 'application/<state>/css'],
            'js_inheritance' => ['application/default/javascript', 'www/asset/themes/<theme>/js', 'application/<state>/javascript'],
            'generated_by' => 'owasys',
        ];
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private function routesConfig(array $plan): array
    {
        $routes = [];
        foreach ($plan['routes'] as $index => $route) {
            $state = $this->stateFromRoute($route);
            $path = (string) ($route['path'] ?? ($state === 'home' ? '/' : '/' . $state));
            $routes[] = [
                'id' => (string) ($route['id'] ?? $state . '.index'),
                'path' => $path,
                'state' => $state,
                'controller' => $state,
                'controller_legacy_alias' => true,
                'class' => null,
                'template' => 'application/' . $state . '/templates/index.score',
                'view' => 'application/' . $state . '/views/index.php',
                'label' => 'menu.' . $state,
                'fsm_state' => $this->stateId($state),
                'dispatch_action' => 'render_route',
                'show_in_menu' => true,
                'order' => ($index + 1) * 10,
            ];
        }
        return ['contract' => 'OPUS_ROUTE_REGISTRY_V1', 'dispatch_model' => 'state-first', 'routes' => $routes];
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private function menuConfig(array $plan): array
    {
        return [
            'contract' => 'OPUS_MENU_ROUTE_PROJECTION_V1',
            'source_fsm' => 'config/application.fsm.json',
            'dispatch_model' => 'state-first',
            'items' => array_map(static fn (string $state): array => ['route' => $state . '.index', 'state' => $state, 'controller' => $state, 'label' => 'menu.' . $state], $plan['controllers']),
        ];
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private function applicationFsmConfig(array $plan): array
    {
        $states = [];
        $routes = $this->routesConfig($plan)['routes'];
        foreach ($routes as $route) {
            $state = (string) ($route['state'] ?? $route['controller'] ?? 'home');
            $states[] = [
                'id' => $this->stateId($state),
                'label' => ucfirst(str_replace('-', ' ', $state)),
                'state' => $state,
                'controller' => $state,
                'controller_legacy_alias' => true,
                'route' => (string) ($route['path'] ?? '/'),
                'view' => (string) ($route['view'] ?? ''),
                'template' => (string) ($route['template'] ?? ''),
                'dispatch' => [
                    'action' => 'render_route',
                    'target' => $state,
                ],
                'visual' => true,
            ];
        }

        $initial = $states[0]['id'] ?? 'home';
        foreach ($states as $state) {
            if (($state['state'] ?? '') === 'home') {
                $initial = (string) $state['id'];
                break;
            }
        }

        $transitions = [];
        foreach ($states as $from) {
            foreach ($states as $to) {
                if (($from['id'] ?? '') === ($to['id'] ?? '')) {
                    continue;
                }
                $transitions[] = [
                    'from' => (string) $from['id'],
                    'event' => 'open_' . (string) $to['id'],
                    'to' => (string) $to['id'],
                    'guard' => 'route_exists',
                    'action' => 'render_route',
                    'dispatch' => [
                        'action' => 'render_route',
                        'target_state' => (string) $to['id'],
                    ],
                    'visual' => true,
                ];
            }
        }

        return [
            'contract' => self::APPLICATION_FSM_CONTRACT,
            'source_of_truth' => 'config',
            'generated_by' => 'owasys',
            'site_id' => $plan['site_id'],
            'dispatch_model' => 'state-first',
            'controller_field' => 'legacy_alias',
            'initial_state' => $initial,
            'states' => $states,
            'transitions' => $transitions,
            'runtime_state' => [
                'storage' => 'site-runtime',
                'history' => 'future-processor',
            ],
        ];
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private function legacyFsmConfig(array $plan): array
    {
        $applicationFsm = $this->applicationFsmConfig($plan);
        return [
            'contract' => self::LEGACY_FSM_CONTRACT,
            'source_of_truth' => 'config/application.fsm.json',
            'initial_state' => strtoupper((string) ($applicationFsm['initial_state'] ?? 'home')),
            'states' => array_map(static fn (array $state): array => [
                'id' => strtoupper((string) ($state['id'] ?? '')),
                'state' => (string) ($state['state'] ?? ''),
                'controller' => (string) ($state['controller'] ?? ''),
                'route' => (string) ($state['route'] ?? ''),
            ], (array) ($applicationFsm['states'] ?? [])),
            'transitions' => array_map(static fn (array $transition): array => [
                'from' => strtoupper((string) ($transition['from'] ?? '')),
                'event' => (string) ($transition['event'] ?? ''),
                'to' => strtoupper((string) ($transition['to'] ?? '')),
            ], (array) ($applicationFsm['transitions'] ?? [])),
        ];
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private function rubricsConfig(array $plan): array
    {
        $rubrics = [];
        foreach ($plan['controllers'] as $state) {
            if ($state === 'home') {
                continue;
            }
            $rubrics[] = ['state' => $state, 'controller' => $state, 'route' => $state . '.index'];
        }
        return ['contract' => 'OPUS_HOME_DEMO_CARD_ROUTE_PROJECTION_V1', 'dispatch_model' => 'state-first', 'rubrics' => $rubrics];
    }

    /** @param array<string,mixed> $plan */
    private function viewModel(string $state, array $plan): string
    {
        $labels = $this->pageLabels($state, $plan);
        $cards = $this->pageCards($state);
        $actions = $this->pageActions($state, $plan);

        return "<?php\ndeclare(strict_types=1);\n\nreturn [\n"
            . "    'state' => " . var_export($state, true) . ",\n"
            . "    'title' => " . var_export($labels['title'], true) . ",\n"
            . "    'subtitle' => " . var_export($labels['subtitle'], true) . ",\n"
            . "    'summary' => " . var_export($labels['summary'], true) . ",\n"
            . "    'kicker' => " . var_export($labels['kicker'], true) . ",\n"
            . "    'cards' => " . var_export($cards, true) . ",\n"
            . "    'actions' => " . var_export($actions, true) . ",\n"
            . "];\n";
    }

    /** @param array<string,mixed> $plan @return array{kicker:string,title:string,subtitle:string,summary:string} */
    private function pageLabels(string $state, array $plan): array
    {
        $siteName = (string) $plan['name'];
        $defaults = [
            'kicker' => 'Generated by OWASYS',
            'title' => ucfirst(str_replace('-', ' ', $state)),
            'subtitle' => 'Generated OPUS FSM state',
            'summary' => 'This page is a rendered state from the generated OPUS application FSM.',
        ];

        $labels = [
            'home' => [
                'kicker' => 'OPUS generated application',
                'title' => $siteName,
                'subtitle' => 'A clean OPUS application generated by OWASYS.',
                'summary' => 'This demo proves the OPUS application tree, routing, assets, view-models, validation, export chain and FSM contract.',
            ],
            'articles' => [
                'kicker' => 'Content demo',
                'title' => 'Articles',
                'subtitle' => 'A starter content state for OPUS pages.',
                'summary' => 'Use this state as a first model for list pages, editorial content and generated navigation transitions.',
            ],
            'about' => [
                'kicker' => 'About this app',
                'title' => 'About',
                'subtitle' => 'A generated application without public/src/resources roots.',
                'summary' => 'OWASYS keeps OPUS structure explicit: config, application/default, application/<state>, www and application.fsm.json.',
            ],
            'contact' => [
                'kicker' => 'Contact page',
                'title' => 'Contact',
                'subtitle' => 'A presentable placeholder for a future form.',
                'summary' => 'This state is ready for a real contact model, validation rules and delivery workflow.',
            ],
        ];

        return $labels[$state] ?? $defaults;
    }

    /** @return list<array{title:string,body:string}> */
    private function pageCards(string $state): array
    {
        if ($state === 'home') {
            return [
                ['title' => 'Standard OPUS tree', 'body' => 'The site uses config, application/default, application/<state> and www only.'],
                ['title' => 'FSM-first application', 'body' => 'The canonical application FSM is stored in config/application.fsm.json.'],
                ['title' => 'State-first dispatch', 'body' => 'Routes and view-models are now dispatched by FSM state; controller remains a legacy alias.'],
            ];
        }

        if ($state === 'articles') {
            return [
                ['title' => 'First generated article', 'body' => 'This card is static seed content generated by the blueprint.'],
                ['title' => 'Second generated article', 'body' => 'The next step is connecting this state to a typed OPUS model.'],
                ['title' => 'Publication workflow', 'body' => 'Draft, review and publish states can be added through the workflow section.'],
            ];
        }

        return [
            ['title' => 'State ready', 'body' => 'The generated state has its own view-model, template, CSS, JavaScript and I18N directory.'],
            ['title' => 'Contract preserved', 'body' => 'No hidden wrapper, no public directory, no src directory and no resources directory.'],
        ];
    }

    /** @param array<string,mixed> $plan @return list<array{label:string,href:string}> */
    private function pageActions(string $state, array $plan): array
    {
        $actions = [['label' => 'Home', 'href' => '/']];
        foreach ($plan['routes'] as $route) {
            if (!is_array($route)) {
                continue;
            }
            $routeState = $this->stateFromRoute($route);
            $path = (string) ($route['path'] ?? '');
            if ($routeState !== $state && $path !== '') {
                $actions[] = ['label' => ucfirst(str_replace('-', ' ', $routeState)), 'href' => $path];
            }
        }
        return array_values(array_unique($actions, SORT_REGULAR));
    }

    private function frontController(): string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);

$siteRoot = dirname(__DIR__);
$routesFile = $siteRoot . '/config/routes.json';
$siteFile = $siteRoot . '/config/site.json';
$fsmFile = $siteRoot . '/config/application.fsm.json';
$routesConfig = json_decode((string) file_get_contents($routesFile), true);
$siteConfig = json_decode((string) file_get_contents($siteFile), true);
$fsmConfig = is_file($fsmFile) ? json_decode((string) file_get_contents($fsmFile), true) : null;
if (!is_array($routesConfig) || !isset($routesConfig['routes']) || !is_array($routesConfig['routes'])) {
    http_response_code(500);
    echo 'OPUS_GENERATED_ROUTES_INVALID';
    exit;
}
if (!is_array($fsmConfig) || ($fsmConfig['contract'] ?? null) !== 'OPUS_APPLICATION_FSM_V1' || empty($fsmConfig['states'])) {
    http_response_code(500);
    echo 'OPUS_GENERATED_FSM_INVALID';
    exit;
}

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = is_string($path) ? rawurldecode($path) : '/';
$path = '/' . trim($path, '/');
$route = null;
foreach ($routesConfig['routes'] as $candidate) {
    if (is_array($candidate) && ($candidate['path'] ?? null) === $path) {
        $route = $candidate;
        break;
    }
}
if (!is_array($route)) {
    http_response_code(404);
    $route = ['state' => 'not-found', 'controller' => 'not-found', 'path' => $path, 'fsm_state' => 'not_found'];
    $page = [
        'state' => 'not-found',
        'title' => 'Page not found',
        'subtitle' => 'No OPUS route matched this path.',
        'summary' => 'Return to the generated home page or add a state route in config/routes.json and config/application.fsm.json.',
        'kicker' => '404',
        'cards' => [],
        'actions' => [['label' => 'Home', 'href' => '/']],
    ];
} else {
    $state = (string) ($route['state'] ?? ($route['controller'] ?? 'home'));
    if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $state)) {
        http_response_code(500);
        echo 'OPUS_GENERATED_STATE_INVALID';
        exit;
    }

    $viewFile = $siteRoot . '/application/' . $state . '/views/index.php';
    $page = is_file($viewFile) ? require $viewFile : ['state' => $state, 'title' => $state, 'subtitle' => 'Generated by OWASYS'];
    if (!is_array($page)) {
        $page = ['state' => $state, 'title' => $state, 'subtitle' => 'Generated by OWASYS'];
    }
}

$routes = array_values(array_filter((array) ($routesConfig['routes'] ?? []), static fn (mixed $route): bool => is_array($route) && ($route['show_in_menu'] ?? true)));
usort($routes, static fn (array $left, array $right): int => ((int) ($left['order'] ?? 0)) <=> ((int) ($right['order'] ?? 0)));
$h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$siteName = is_array($siteConfig) ? (string) ($siteConfig['site_name'] ?? 'OPUS Application') : 'OPUS Application';
$theme = is_array($siteConfig) ? (string) ($siteConfig['theme'] ?? 'starter') : 'starter';
$currentState = (string) ($route['fsm_state'] ?? ($route['state'] ?? 'unknown'));
$cards = array_values(array_filter((array) ($page['cards'] ?? []), 'is_array'));
$actions = array_values(array_filter((array) ($page['actions'] ?? []), 'is_array'));

echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $h((string) ($page['title'] ?? $siteName)) . '</title><link rel="stylesheet" href="/asset/themes/' . $h($theme) . '/css/theme.css"></head><body class="opus-generated-site" data-opus-fsm="OPUS_APPLICATION_FSM_V1" data-opus-dispatch="state-first" data-opus-state="' . $h($currentState) . '"><header class="opus-top"><a class="opus-brand" href="/">' . $h($siteName) . '</a><nav class="opus-nav">';
foreach ($routes as $menuRoute) {
    $href = (string) ($menuRoute['path'] ?? '/');
    $state = (string) ($menuRoute['state'] ?? ($menuRoute['controller'] ?? 'home'));
    $label = ucfirst(str_replace('-', ' ', $state));
    $active = $href === $path ? ' aria-current="page"' : '';
    echo '<a' . $active . ' href="' . $h($href) . '">' . $h($label) . '</a>';
}
echo '</nav></header><main class="opus-shell"><section class="opus-hero"><p class="opus-kicker">' . $h((string) ($page['kicker'] ?? 'Generated by OWASYS')) . '</p><h1>' . $h((string) ($page['title'] ?? $siteName)) . '</h1><p class="opus-lead">' . $h((string) ($page['summary'] ?? ($page['subtitle'] ?? 'Generated OPUS application'))) . '</p><div class="opus-actions">';
foreach ($actions as $action) {
    echo '<a class="opus-button" href="' . $h((string) ($action['href'] ?? '#')) . '">' . $h((string) ($action['label'] ?? 'Open')) . '</a>';
}
echo '</div><p class="opus-fsm-badge">FSM state: ' . $h($currentState) . '</p></section><section class="opus-grid">';
foreach ($cards as $card) {
    echo '<article class="opus-card"><h2>' . $h((string) ($card['title'] ?? 'Section')) . '</h2><p>' . $h((string) ($card['body'] ?? '')) . '</p></article>';
}
echo '</section></main><footer class="opus-footer">Generated by OWASYS · state-first OPUS_APPLICATION_FSM_V1</footer><script src="/asset/themes/' . $h($theme) . '/js/theme.js"></script></body></html>';
PHP;
    }

    private function defaultCss(): string
    {
        return "body.opus-generated-site{margin:0;font-family:system-ui,Segoe UI,Arial,sans-serif;background:#eef3f8;color:#162336}.opus-card{display:block;margin:12px 0;padding:16px;background:#fff;border:1px solid #d7e0eb;border-radius:12px}\n";
    }

    private function themeCss(string $theme): string
    {
        return <<<CSS
:root{--opus-blue:#15395f;--opus-accent:#4fd1ff;--opus-ink:#122033;--opus-muted:#64748b;--opus-surface:#ffffff;--opus-line:#d8e3ef;--opus-bg:#eef5fb}
*{box-sizing:border-box}body.opus-generated-site{margin:0;font-family:Inter,Segoe UI,system-ui,Arial,sans-serif;background:radial-gradient(circle at top left,#dff7ff 0,#eef5fb 36%,#f7fafc 100%);color:var(--opus-ink)}.opus-top{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:22px clamp(20px,5vw,64px);background:rgba(255,255,255,.82);backdrop-filter:blur(18px);border-bottom:1px solid rgba(98,127,164,.22);position:sticky;top:0;z-index:10}.opus-brand{font-weight:900;color:var(--opus-blue);text-decoration:none;letter-spacing:-.03em}.opus-nav{display:flex;flex-wrap:wrap;gap:10px}.opus-nav a{color:#25415f;text-decoration:none;font-weight:750;padding:9px 12px;border-radius:999px}.opus-nav a:hover,.opus-nav a[aria-current=page]{background:#15395f;color:#fff}.opus-shell{width:min(1120px,calc(100% - 32px));margin:0 auto;padding:58px 0 44px}.opus-hero{border:1px solid rgba(98,127,164,.22);background:linear-gradient(135deg,rgba(255,255,255,.94),rgba(236,250,255,.88));border-radius:30px;padding:clamp(28px,5vw,58px);box-shadow:0 24px 70px rgba(21,57,95,.13)}.opus-kicker,.opus-fsm-badge{display:inline-flex;margin:0 0 16px;padding:7px 12px;border-radius:999px;background:#dff7ff;color:#0d5a7a;font-weight:900;text-transform:uppercase;letter-spacing:.08em;font-size:12px}.opus-fsm-badge{margin:24px 0 0;background:#e7fbe9;color:#126b31}.opus-hero h1{font-size:clamp(38px,7vw,72px);line-height:.95;margin:0 0 18px;color:#10233b;letter-spacing:-.065em}.opus-lead{font-size:clamp(18px,2.6vw,24px);line-height:1.45;max-width:820px;color:#475569;margin:0}.opus-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}.opus-button{display:inline-flex;align-items:center;justify-content:center;padding:12px 16px;border-radius:14px;text-decoration:none;background:var(--opus-blue);color:#fff;font-weight:850;box-shadow:0 10px 28px rgba(21,57,95,.24)}.opus-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px;margin-top:22px}.opus-card{background:rgba(255,255,255,.92);border:1px solid rgba(98,127,164,.22);border-radius:22px;padding:22px;box-shadow:0 16px 42px rgba(21,57,95,.08)}.opus-card h2{margin:0 0 10px;font-size:20px;color:#15395f}.opus-card p{margin:0;color:#64748b;line-height:1.55}.opus-footer{text-align:center;padding:30px;color:#64748b}
CSS;
    }

    private function stateId(string $state): string
    {
        return strtolower(str_replace('-', '_', $state));
    }

    /** @param array<string,mixed> $route */
    private function stateFromRoute(array $route): string
    {
        $state = (string) ($route['state'] ?? ($route['controller'] ?? 'home'));
        if ($state === '' || preg_match('/^[a-z0-9][a-z0-9_-]*$/', $state) !== 1) {
            throw new RuntimeException('OWASYS_SCAFFOLD_ROUTE_STATE_INVALID');
        }
        return $state;
    }

    private function stateFromPath(string $path): ?string
    {
        if (preg_match('#/application/([a-z0-9_-]+)/#', $path, $matches) !== 1) {
            return null;
        }
        return $matches[1] === 'default' ? null : $matches[1];
    }

    /** @param array<string,mixed> $source */
    private function stringField(array $source, string $field): string
    {
        $value = $source[$field] ?? null;
        if (!is_string($value) || $value === '') {
            throw new RuntimeException('OWASYS_SCAFFOLD_REQUIRED_STRING_INVALID: ' . $field);
        }
        return $value;
    }

    /** @param array<string,mixed> $source */
    private function relativePathField(array $source, string $field): string
    {
        $value = str_replace('\\', '/', $this->stringField($source, $field));
        if (str_starts_with($value, '/') || preg_match('/^[A-Za-z]:/', $value) === 1) {
            throw new RuntimeException('OWASYS_SCAFFOLD_PATH_MUST_BE_RELATIVE: ' . $field);
        }
        $this->assertSafePath($value, $field);
        return trim($value, '/');
    }

    /** @param array<string,mixed> $source @return list<string> */
    private function stringListField(array $source, string $field): array
    {
        $items = $source[$field] ?? null;
        if (!is_array($items) || $items === []) {
            throw new RuntimeException('OWASYS_SCAFFOLD_REQUIRED_LIST_INVALID: ' . $field);
        }
        $result = [];
        foreach ($items as $item) {
            if (!is_string($item) || preg_match('/^[a-z0-9][a-z0-9_-]*$/', $item) !== 1) {
                throw new RuntimeException('OWASYS_SCAFFOLD_LIST_ITEM_INVALID: ' . $field);
            }
            $result[] = $item;
        }
        return array_values(array_unique($result));
    }

    /** @param array<string,mixed> $source @return list<array<string,mixed>> */
    private function arrayListField(array $source, string $field): array
    {
        $items = $source[$field] ?? null;
        if (!is_array($items)) {
            throw new RuntimeException('OWASYS_SCAFFOLD_REQUIRED_ARRAY_INVALID: ' . $field);
        }
        return array_values(array_filter($items, static fn (mixed $item): bool => is_array($item)));
    }

    /** @param array<string,mixed> $source @return list<string> */
    private function pathListField(array $source, string $field, string $siteRoot): array
    {
        $items = $source[$field] ?? null;
        if (!is_array($items) || $items === []) {
            throw new RuntimeException('OWASYS_SCAFFOLD_REQUIRED_PATH_LIST_INVALID: ' . $field);
        }
        $paths = [];
        foreach ($items as $item) {
            if (!is_string($item)) {
                throw new RuntimeException('OWASYS_SCAFFOLD_PATH_LIST_ITEM_INVALID: ' . $field);
            }
            $path = trim(str_replace('\\', '/', $item), '/');
            $this->assertPathUnderRoot($path, $siteRoot, $field);
            $paths[] = $path;
        }
        return array_values(array_unique($paths));
    }

    /** @param array<string,mixed> $source @return list<array{path:string,kind:string,content_source:string}> */
    private function fileListField(array $source, string $field, string $siteRoot): array
    {
        $items = $source[$field] ?? null;
        if (!is_array($items) || $items === []) {
            throw new RuntimeException('OWASYS_SCAFFOLD_REQUIRED_FILE_LIST_INVALID: ' . $field);
        }
        $files = [];
        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['path'], $item['kind'], $item['content_source'])) {
                throw new RuntimeException('OWASYS_SCAFFOLD_FILE_DESCRIPTOR_INVALID');
            }
            $path = trim(str_replace('\\', '/', (string) $item['path']), '/');
            $this->assertPathUnderRoot($path, $siteRoot, $field);
            $files[] = [
                'path' => $path,
                'kind' => (string) $item['kind'],
                'content_source' => (string) $item['content_source'],
            ];
        }
        return $files;
    }

    /** @param list<array{path:string,kind:string,content_source:string}> $files */
    private function fileExistsInPlan(array $files, string $path): bool
    {
        foreach ($files as $file) {
            if ($file['path'] === $path) {
                return true;
            }
        }
        return false;
    }

    private function assertPathUnderRoot(string $path, string $siteRoot, string $field): void
    {
        $this->assertSafePath($path, $field);
        if ($path !== $siteRoot && !str_starts_with($path, $siteRoot . '/')) {
            throw new RuntimeException('OWASYS_SCAFFOLD_PATH_OUTSIDE_ROOT: ' . $field . ':' . $path);
        }
    }

    private function assertSafePath(string $path, string $field): void
    {
        foreach (explode('/', $path) as $segment) {
            if (in_array($segment, self::FORBIDDEN_SEGMENTS, true)) {
                throw new RuntimeException('OWASYS_SCAFFOLD_PATH_FORBIDDEN_SEGMENT: ' . $field . ':' . $segment);
            }
        }
    }

    private function absolutePath(string $relativePath): string
    {
        return rtrim($this->opusRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    }

    /** @param array<string,mixed> $data */
    private function json(array $data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}
