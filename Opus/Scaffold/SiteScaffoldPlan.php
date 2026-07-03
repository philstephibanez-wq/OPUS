<?php
declare(strict_types=1);

namespace Opus\Scaffold;

/**
 * OPUS site scaffold.
 *
 * Eternal ASAP contract:
 * sites/<site>/config
 * sites/<site>/application/default
 * sites/<site>/application/<controller>
 * sites/<site>/www/asset/themes/<theme>
 */
final class SiteScaffoldPlan implements ScaffoldPlanInterface, SiteScaffoldPlanInterface
{
    private const CONTRACT = 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL';

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
        $controllers = ['home', 'architecture', 'router', 'modules', 'controllers', 'views', 'models', 'i18n'];
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

        foreach ($controllers as $controller) {
            foreach (['', '/acl', '/helpers', '/css', '/javascript', '/local', '/local/fr', '/local/en', '/local/es', '/models', '/templates', '/views'] as $suffix) {
                $directories[] = "sites/{$site}/application/{$controller}{$suffix}";
            }
        }

        $entries = [];
        foreach (array_values(array_unique($directories)) as $directory) {
            $entries[] = ScaffoldEntry::directory($directory);
        }

        $entries[] = ScaffoldEntry::file("sites/{$site}/opus-site.json", $this->json([
            'site_id' => $site,
            'contract' => self::CONTRACT,
            'asap_reference' => 'https://asap.logandplay.org/ASAP_PHP_DEMO/fr/articles',
        ]));
        $entries[] = ScaffoldEntry::file("sites/{$site}/config/site.json", $this->json($this->siteConfig($site)));
        $entries[] = ScaffoldEntry::file("sites/{$site}/config/routes.json", $this->json($this->routesConfig($controllers)));
        $entries[] = ScaffoldEntry::file("sites/{$site}/config/menu.json", $this->json($this->menuConfig($controllers)));
        $entries[] = ScaffoldEntry::file("sites/{$site}/config/fsm.json", $this->json($this->fsmConfig($controllers)));
        $entries[] = ScaffoldEntry::file("sites/{$site}/config/rubrics.json", $this->json($this->rubricsConfig($controllers)));
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

        foreach ($controllers as $controller) {
            $entries[] = ScaffoldEntry::file("sites/{$site}/application/{$controller}/templates/index.score", "<section class=\"opus-card\"><h2>{{ page.title }}</h2><p>{{ page.subtitle }}</p></section>\n");
            $entries[] = ScaffoldEntry::file("sites/{$site}/application/{$controller}/css/{$controller}.css", "/* {$controller} */\n");
            $entries[] = ScaffoldEntry::file("sites/{$site}/application/{$controller}/javascript/{$controller}.js", "document.documentElement.dataset.opusControllerLayer='{$controller}';\n");
            $entries[] = ScaffoldEntry::file("sites/{$site}/application/{$controller}/local/fr/i18n.json", $this->json($this->controllerI18n($controller, 'fr')));
            $entries[] = ScaffoldEntry::file("sites/{$site}/application/{$controller}/local/en/i18n.json", $this->json($this->controllerI18n($controller, 'en')));
            $entries[] = ScaffoldEntry::file("sites/{$site}/application/{$controller}/local/es/i18n.json", $this->json($this->controllerI18n($controller, 'es')));
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
            'default_root' => 'application/default',
            'controller_root_pattern' => 'application/<controller>',
            'public_root' => 'www',
            'asset_root' => 'www/asset',
            'theme_root_pattern' => 'www/asset/themes/<theme>',
            'css_inheritance' => ['application/default/css', 'www/asset/themes/<theme>/css', 'application/<controller>/css'],
            'js_inheritance' => ['application/default/javascript', 'www/asset/themes/<theme>/js', 'application/<controller>/javascript'],
        ];
    }

    /** @param list<string> $controllers @return array<string,mixed> */
    private function routesConfig(array $controllers): array
    {
        $routes = [];
        foreach ($controllers as $index => $controller) {
            $routes[] = [
                'id' => $controller . '.index',
                'path' => $controller === 'home' ? '/' : '/' . $controller,
                'controller' => $controller,
                'action' => 'index',
                'template' => 'application/' . $controller . '/templates/index.score',
                'label' => 'menu.' . $controller,
                'acl' => 'public',
                'fsm_state' => strtoupper(str_replace('-', '_', $controller)),
                'show_in_menu' => true,
                'show_on_home' => $controller !== 'home',
                'order' => ($index + 1) * 10,
            ];
        }
        return ['contract' => 'OPUS_ROUTE_REGISTRY_V1', 'routes' => $routes];
    }

    /** @param list<string> $controllers @return array<string,mixed> */
    private function menuConfig(array $controllers): array
    {
        return ['contract' => 'OPUS_MENU_ROUTE_PROJECTION_V1', 'items' => array_map(static fn (string $c): array => ['route' => $c . '.index', 'controller' => $c, 'label' => 'menu.' . $c], $controllers)];
    }

    /** @param list<string> $controllers @return array<string,mixed> */
    private function fsmConfig(array $controllers): array
    {
        return ['contract' => 'OPUS_FSM_REGISTRY_V1', 'initial_state' => 'HOME', 'states' => array_map(static fn (string $c): array => ['id' => strtoupper(str_replace('-', '_', $c)), 'controller' => $c], $controllers), 'transitions' => []];
    }

    /** @param list<string> $controllers @return array<string,mixed> */
    private function rubricsConfig(array $controllers): array
    {
        return ['contract' => 'OPUS_HOME_DEMO_CARD_ROUTE_PROJECTION_V1', 'rubrics' => array_values(array_map(static fn (string $c): array => ['controller' => $c, 'route' => $c . '.index'], array_filter($controllers, static fn (string $c): bool => $c !== 'home')))];
    }

    /** @return array<string,string> */
    private function defaultI18n(string $locale): array
    {
        return ['language' => $locale === 'fr' ? 'Langue' : 'Language', 'menu.home' => 'Home', 'menu.architecture' => 'Architecture', 'menu.router' => 'Router', 'menu.modules' => 'Modules', 'menu.controllers' => 'Controllers', 'menu.views' => 'Views', 'menu.models' => 'Models', 'menu.i18n' => 'I18N'];
    }

    /** @return array<string,string> */
    private function controllerI18n(string $controller, string $locale): array
    {
        return ['page.title' => strtoupper($controller), 'page.subtitle' => 'OPUS ASAP contract controller: ' . $controller];
    }

    private function frontController(): string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);

$siteRoot = dirname(__DIR__);
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$path = '/' . trim($path, '/');
if ($path === '/') {
    $controller = 'home';
} else {
    $controller = preg_replace('/\.php$/', '', basename($path)) ?: 'home';
}
if (!preg_match('/^[A-Za-z0-9_-]+$/', $controller)) {
    http_response_code(400);
    echo 'OPUS_CONTROLLER_INVALID';
    exit;
}
$legacy = $siteRoot . '/application/' . $controller . '/views/legacy-public-entry.php';
if (is_file($legacy)) {
    require $legacy;
    exit;
}
header('Content-Type: text/html; charset=UTF-8');
echo '<!doctype html><html><head><meta charset="utf-8"><title>OPUS</title><link rel="stylesheet" href="/asset/css/default-default.css"><link rel="stylesheet" href="/asset/themes/starter/css/theme.css"></head><body class="opus-asap-site"><main class="opus-shell"><h1>OPUS</h1><p>Controller: ' . htmlspecialchars($controller, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p></main></body></html>';
PHP;
    }

    /** @param array<string,mixed> $data */
    private function json(array $data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}
