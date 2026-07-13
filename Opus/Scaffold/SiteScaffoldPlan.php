<?php
declare(strict_types=1);

namespace Opus\Scaffold;

/**
 * OPUS site scaffold.
 *
 * Eternal OPUS contract:
 * sites/<site>/config
 * sites/<site>/application/default
 * sites/<site>/application/states/<state>
 * sites/<site>/www/asset/themes/<theme>
 */
final class SiteScaffoldPlan implements ScaffoldPlanInterface, SiteScaffoldPlanInterface
{
    private const CONTRACT = 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL';
    private const APPLICATION_FSM_CONTRACT = 'OPUS_APPLICATION_FSM_V1';
    private const LEGACY_FSM_CONTRACT = 'OPUS_FSM_REGISTRY_V1';

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
        $states = ['home', 'architecture', 'router', 'modules', 'controllers', 'views', 'models', 'i18n'];
        $directories = [
            "sites/{$site}/config",
            "sites/{$site}/application",
            "sites/{$site}/application/default",
            "sites/{$site}/application/default/acl",
            "sites/{$site}/application/default/helpers",
            "sites/{$site}/application/default/css",
            "sites/{$site}/application/default/javascript",
            "sites/{$site}/application/default/local",
            "sites/{$site}/application/default/local/fr",
            "sites/{$site}/application/default/local/en",
            "sites/{$site}/application/default/local/es",
            "sites/{$site}/application/default/models",
            "sites/{$site}/application/default/templates",
            "sites/{$site}/application/default/templates/components",
            "sites/{$site}/application/default/views",
            "sites/{$site}/application/states",
            "sites/{$site}/www",
            "sites/{$site}/www/asset",
            "sites/{$site}/www/asset/css",
            "sites/{$site}/www/asset/js",
            "sites/{$site}/www/asset/themes",
            "sites/{$site}/www/asset/themes/starter",
            "sites/{$site}/www/asset/themes/starter/css",
            "sites/{$site}/www/asset/themes/starter/js",
            "sites/{$site}/www/asset/themes/starter/img",
        ];

        foreach ($states as $state) {
            foreach (['', '/acl', '/helpers', '/css', '/javascript', '/local', '/local/fr', '/local/en', '/local/es', '/models', '/templates', '/views'] as $suffix) {
                $directories[] = "sites/{$site}/application/states/{$state}{$suffix}";
            }
        }

        $entries = [];
        foreach (array_values(array_unique($directories)) as $directory) {
            $entries[] = ScaffoldEntry::directory($directory);
        }

        $entries[] = ScaffoldEntry::file("sites/{$site}/opus-site.json", $this->json([
            'site_id' => $site,
            'contract' => self::CONTRACT,
            'states_root' => 'application/states',
            'dispatch_model' => 'state-first',
        ]));
        $entries[] = ScaffoldEntry::file("sites/{$site}/config/site.json", $this->json($this->siteConfig($site)));
        $entries[] = ScaffoldEntry::file("sites/{$site}/config/routes.json", $this->json($this->routesConfig($states)));
        $entries[] = ScaffoldEntry::file("sites/{$site}/config/menu.json", $this->json($this->menuConfig($states)));
        $entries[] = ScaffoldEntry::file("sites/{$site}/config/application.fsm.json", $this->json($this->applicationFsmConfig($site, $states)));
        $entries[] = ScaffoldEntry::file("sites/{$site}/config/fsm.json", $this->json($this->legacyFsmConfig($site, $states)));
        $entries[] = ScaffoldEntry::file("sites/{$site}/config/rubrics.json", $this->json($this->rubricsConfig($states)));
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/templates/layout.score", "<!doctype html>\n<html lang=\"{{ lang }}\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>{{ page.title }}</title>\n{{{ assets.css }}}\n</head>\n<body class=\"opus-asap-site\">\n{{{ common.header }}}\n<main id=\"main-content\" class=\"opus-shell\">{{{ content }}}</main>\n{{{ common.footer }}}\n{{{ assets.js }}}\n</body>\n</html>\n");
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/templates/components/header.score", "<header class=\"opus-header\"><h1>{{ site.name }}</h1><nav>{{{ common.menu }}}</nav></header>\n");
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/templates/components/footer.score", "<footer class=\"opus-footer\">{{ site.contract }}</footer>\n");
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/templates/components/menu-item.score", "<a class=\"{{ menu_item.active_class }}\" href=\"{{ menu_item.path }}\">{{ menu_item.label }}</a>\n");
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/css/default.css", "body.opus-asap-site{margin:0;font-family:system-ui,Segoe UI,Arial,sans-serif;background:#eef3f8;color:#162336}.opus-header,.opus-footer{background:#24466d;color:#fff;padding:24px}.opus-shell{padding:24px;min-height:60vh}.opus-card{display:block;margin:12px 0;padding:16px;background:#fff;border:1px solid #d7e0eb;border-radius:12px}\n");
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/javascript/default.js", "document.documentElement.dataset.opusDefaultLayer='loaded';\n");
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/local/fr/i18n.json", $this->json($this->defaultI18n('fr')));
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/local/en/i18n.json", $this->json($this->defaultI18n('en')));
        $entries[] = ScaffoldEntry::file("sites/{$site}/application/default/local/es/i18n.json", $this->json($this->defaultI18n('es')));
        $entries[] = ScaffoldEntry::file("sites/{$site}/www/asset/themes/starter/css/theme.css", "body.opus-asap-site{--opus-theme:starter}\n");
        $entries[] = ScaffoldEntry::file("sites/{$site}/www/asset/themes/starter/js/theme.js", "document.documentElement.dataset.opusThemeLayer='starter';\n");
        $entries[] = ScaffoldEntry::file("sites/{$site}/www/index.php", $this->frontController());

        foreach ($states as $state) {
            $base = "sites/{$site}/application/states/{$state}";
            $entries[] = ScaffoldEntry::file($base . "/templates/index.score", "<section class=\"opus-card\"><h2>{{ page.title }}</h2><p>{{ page.subtitle }}</p></section>\n");
            $entries[] = ScaffoldEntry::file($base . "/views/index.php", $this->stateViewModel($state));
            $entries[] = ScaffoldEntry::file($base . "/css/{$state}.css", "/* {$state} */\n");
            $entries[] = ScaffoldEntry::file($base . "/javascript/{$state}.js", "document.documentElement.dataset.opusStateLayer='{$state}';\n");
            $entries[] = ScaffoldEntry::file($base . "/local/fr/i18n.json", $this->json($this->stateI18n($state, 'fr')));
            $entries[] = ScaffoldEntry::file($base . "/local/en/i18n.json", $this->json($this->stateI18n($state, 'en')));
            $entries[] = ScaffoldEntry::file($base . "/local/es/i18n.json", $this->json($this->stateI18n($state, 'es')));
        }

        return $entries;
    }

    /** @return array<string,mixed> */
    private function siteConfig(string $site): array
    {
        return [
            'site_id' => $site,
            'site_name' => 'OPUS ' . $site,
            'contract' => self::CONTRACT,
            'default_locale' => 'fr',
            'locales' => ['fr', 'en', 'es'],
            'theme' => 'starter',
            'application_root' => 'application',
            'states_root' => 'application/states',
            'default_root' => 'application/default',
            'application_fsm' => 'config/application.fsm.json',
            'fsm_legacy_projection' => 'config/fsm.json',
            'fsm_contract' => self::APPLICATION_FSM_CONTRACT,
            'dispatch_model' => 'state-first',
            'controller_field' => 'legacy_alias',
            'public_root' => 'www',
            'asset_root' => 'www/asset',
            'theme_root_pattern' => 'www/asset/themes/<theme>',
            'css_inheritance' => ['application/default/css', 'www/asset/themes/<theme>/css', 'application/states/<state>/css'],
            'js_inheritance' => ['application/default/javascript', 'www/asset/themes/<theme>/js', 'application/states/<state>/javascript'],
        ];
    }

    /** @param list<string> $states @return array<string,mixed> */
    private function routesConfig(array $states): array
    {
        $routes = [];
        foreach ($states as $index => $state) {
            $routes[] = [
                'id' => $state . '.index',
                'path' => $state === 'home' ? '/' : '/' . $state,
                'state' => $state,
                'controller' => $state,
                'controller_legacy_alias' => true,
                'action' => 'index',
                'template' => 'application/states/' . $state . '/templates/index.score',
                'view' => 'application/states/' . $state . '/views/index.php',
                'label' => 'menu.' . $state,
                'acl' => 'public',
                'fsm_state' => $state,
                'dispatch_action' => 'render_route',
                'show_in_menu' => true,
                'show_on_home' => $state !== 'home',
                'order' => ($index + 1) * 10,
            ];
        }
        return ['contract' => 'OPUS_ROUTE_REGISTRY_V1', 'dispatch_model' => 'state-first', 'routes' => $routes];
    }

    /** @param list<string> $states @return array<string,mixed> */
    private function menuConfig(array $states): array
    {
        return [
            'contract' => 'OPUS_MENU_ROUTE_PROJECTION_V1',
            'source_fsm' => 'config/application.fsm.json',
            'dispatch_model' => 'state-first',
            'items' => array_map(static fn (string $state): array => ['route' => $state . '.index', 'state' => $state, 'controller' => $state, 'label' => 'menu.' . $state], $states),
        ];
    }

    /** @param list<string> $states @return array<string,mixed> */
    private function applicationFsmConfig(string $site, array $states): array
    {
        $fsmStates = array_map(static fn (string $state): array => [
            'id' => $state,
            'state' => $state,
            'controller' => $state,
            'controller_legacy_alias' => true,
            'route' => $state === 'home' ? '/' : '/' . $state,
            'view' => 'application/states/' . $state . '/views/index.php',
            'template' => 'application/states/' . $state . '/templates/index.score',
            'dispatch' => ['action' => 'render_route', 'target' => $state],
            'visual' => true,
        ], $states);

        $transitions = [];
        foreach ($states as $from) {
            foreach ($states as $to) {
                if ($from === $to) {
                    continue;
                }
                $transitions[] = [
                    'from' => $from,
                    'event' => 'open_' . $to,
                    'to' => $to,
                    'guard' => 'route_exists',
                    'action' => 'render_route',
                    'dispatch' => ['action' => 'render_route', 'target_state' => $to],
                    'visual' => true,
                ];
            }
        }

        return [
            'contract' => self::APPLICATION_FSM_CONTRACT,
            'source_of_truth' => 'config',
            'site_id' => $site,
            'dispatch_model' => 'state-first',
            'controller_field' => 'legacy_alias',
            'initial_state' => 'home',
            'states' => $fsmStates,
            'transitions' => $transitions,
        ];
    }

    /** @param list<string> $states @return array<string,mixed> */
    private function legacyFsmConfig(string $site, array $states): array
    {
        return [
            'contract' => self::LEGACY_FSM_CONTRACT,
            'source_of_truth' => 'config/application.fsm.json',
            'site_id' => $site,
            'initial_state' => 'HOME',
            'states' => array_map(static fn (string $state): array => ['id' => strtoupper(str_replace('-', '_', $state)), 'state' => $state, 'controller' => $state], $states),
            'transitions' => [],
        ];
    }

    /** @param list<string> $states @return array<string,mixed> */
    private function rubricsConfig(array $states): array
    {
        return ['contract' => 'OPUS_HOME_DEMO_CARD_ROUTE_PROJECTION_V1', 'dispatch_model' => 'state-first', 'rubrics' => array_values(array_map(static fn (string $state): array => ['state' => $state, 'controller' => $state, 'route' => $state . '.index'], array_filter($states, static fn (string $state): bool => $state !== 'home')))];
    }

    /** @return array<string,string> */
    private function defaultI18n(string $locale): array
    {
        return ['language' => $locale === 'fr' ? 'Langue' : 'Language', 'menu.home' => 'Home', 'menu.architecture' => 'Architecture', 'menu.router' => 'Router', 'menu.modules' => 'Modules', 'menu.controllers' => 'Controllers', 'menu.views' => 'Views', 'menu.models' => 'Models', 'menu.i18n' => 'I18N'];
    }

    /** @return array<string,string> */
    private function stateI18n(string $state, string $locale): array
    {
        return ['page.title' => strtoupper($state), 'page.subtitle' => 'OPUS state-first node: ' . $state];
    }

    private function stateViewModel(string $state): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nreturn [\n    'state' => " . var_export($state, true) . ",\n    'title' => " . var_export(strtoupper($state), true) . ",\n    'subtitle' => " . var_export('OPUS state-first node: ' . $state, true) . ",\n];\n";
    }

    private function frontController(): string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);

$siteRoot = dirname(__DIR__);
$routesFile = $siteRoot . '/config/routes.json';
$routesConfig = json_decode((string) file_get_contents($routesFile), true);
if (!is_array($routesConfig) || !is_array($routesConfig['routes'] ?? null)) {
    http_response_code(500);
    echo 'OPUS_ROUTES_INVALID';
    exit;
}
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
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
    echo 'OPUS_ROUTE_NOT_FOUND';
    exit;
}
$state = (string) ($route['state'] ?? $route['controller'] ?? 'home');
if (!preg_match('/^[A-Za-z0-9_-]+$/', $state)) {
    http_response_code(400);
    echo 'OPUS_STATE_INVALID';
    exit;
}
$view = $siteRoot . '/application/states/' . $state . '/views/index.php';
$page = is_file($view) ? require $view : ['title' => $state, 'subtitle' => 'OPUS state'];
$h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
header('Content-Type: text/html; charset=UTF-8');
echo '<!doctype html><html><head><meta charset="utf-8"><title>OPUS</title><link rel="stylesheet" href="/asset/themes/starter/css/theme.css"></head><body class="opus-asap-site" data-opus-dispatch="state-first" data-opus-state="' . $h($state) . '"><main class="opus-shell"><h1>' . $h((string) ($page['title'] ?? $state)) . '</h1><p>' . $h((string) ($page['subtitle'] ?? '')) . '</p></main></body></html>';
PHP;
    }

    /** @param array<string,mixed> $data */
    private function json(array $data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}
