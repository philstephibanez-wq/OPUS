<?php
declare(strict_types=1);

namespace Opus\Scaffold;

use Opus\File\Json;

/** Canonical scaffold for an autonomous OPUS site/application. */
final class SiteScaffoldPlan implements ScaffoldPlanInterface, SiteScaffoldPlanInterface
{
    /** @var list<string> */
    private const MODULES = [
        'home',
        'architecture',
        'router',
        'modules',
        'controllers',
        'views',
        'models',
        'i18n',
    ];

    private function __construct(private readonly string $siteId)
    {
    }

    public static function forSite(string $siteId): self
    {
        $siteId = trim(strtolower($siteId));
        if (preg_match('/^[a-z][a-z0-9-]*$/', $siteId) !== 1) {
            throw new \InvalidArgumentException('OPUS_APPLICATION_ID_INVALID:' . $siteId);
        }
        return new self($siteId);
    }

    public function rootRelativePath(): string
    {
        return 'sites/' . $this->siteId;
    }

    /** @return list<ScaffoldEntry> */
    public function entries(): array
    {
        $site = $this->siteId;
        $directories = [
            "sites/{$site}/config",
            "sites/{$site}/application",
            "sites/{$site}/application/default",
            "sites/{$site}/application/default/helpers",
            "sites/{$site}/application/default/layouts",
            "sites/{$site}/application/default/local",
            "sites/{$site}/application/default/models",
            "sites/{$site}/application/default/navigation",
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
            "sites/{$site}/www/asset/vendor",
        ];
        foreach (self::MODULES as $module) {
            foreach (['', '/acl', '/helpers', '/javascript', '/local', '/models', '/templates', '/views'] as $suffix) {
                $directories[] = "sites/{$site}/application/{$module}{$suffix}";
            }
        }

        $entries = array_map(
            static fn (string $directory): ScaffoldEntry => ScaffoldEntry::directory($directory),
            array_values(array_unique($directories))
        );

        $files = [
            "sites/{$site}/opus-site.json" => $this->json([
                'site_id' => $site,
                'contract' => 'OPUS_SITE_STANDARD_CONTRACT_CORE',
                'dispatch_model' => 'fsm-module-first',
            ]),
            "sites/{$site}/config/site.json" => $this->json($this->siteConfig()),
            "sites/{$site}/config/routes.json" => $this->json($this->routesConfig()),
            "sites/{$site}/config/menu.json" => $this->json($this->menuConfig()),
            "sites/{$site}/config/application.fsm.json" => $this->json($this->fsmConfig()),
            "sites/{$site}/config/rubrics.json" => $this->json([
                'contract' => 'OPUS_RUBRIC_REGISTRY_V1',
                'rubrics' => [],
            ]),
            "sites/{$site}/config/acl.json" => $this->json($this->aclConfig()),
            "sites/{$site}/config/sso.json" => $this->json($this->ssoConfig()),
            "sites/{$site}/application/default/bootstrap.php" => $this->bootstrap(),
            "sites/{$site}/application/default/Application.php" => $this->applicationClass(),
            "sites/{$site}/application/default/layouts/layout.score" => $this->layoutTemplate(),
            "sites/{$site}/application/default/templates/error.score" => '<section class="opus-card opus-error" role="alert"><h2>{{ error.title }}</h2><p>{{ error.message }}</p><code>{{ error.code }}</code></section>' . "\n",
            "sites/{$site}/application/default/templates/components/header.score" => '<header class="opus-header"><h1>{{ site.name }}</h1><nav class="opus-menu">{{{ common.menu }}}</nav></header>' . "\n",
            "sites/{$site}/application/default/templates/components/footer.score" => '<footer class="opus-footer">{{ site.contract }}</footer>' . "\n",
            "sites/{$site}/application/default/templates/components/menu-item.score" => '<a class="{{ menu_item.active_class }}" href="{{ menu_item.path }}">{{ menu_item.label }}</a>' . "\n",
            "sites/{$site}/application/default/templates/components/stylesheet.score" => '<link rel="stylesheet" href="{{ asset.href }}">' . "\n",
            "sites/{$site}/application/default/templates/components/script.score" => '<script src="{{ asset.src }}" defer></script>' . "\n",
            "sites/{$site}/application/default/navigation/menu.json" => $this->json($this->menuConfig()),
            "sites/{$site}/www/asset/css/default.css" => $this->defaultCss(),
            "sites/{$site}/www/asset/js/default.js" => "document.documentElement.dataset.opusRuntime='ready';\n",
            "sites/{$site}/www/asset/themes/starter/css/theme.css" => "body.opus-site{--opus-theme:starter}\n",
            "sites/{$site}/www/asset/themes/starter/js/theme.js" => "document.documentElement.dataset.opusTheme='starter';\n",
            "sites/{$site}/www/index.php" => $this->frontController(),
        ];

        foreach (['fr', 'en', 'es'] as $locale) {
            $files["sites/{$site}/application/default/local/{$locale}.json"] = $this->json(
                $this->defaultCatalog($locale)
            );
        }
        foreach (self::MODULES as $module) {
            $files["sites/{$site}/application/{$module}/templates/index.score"] = '<section class="opus-card"><h2>{{ page.title }}</h2><p>{{ page.subtitle }}</p></section>' . "\n";
            $files["sites/{$site}/application/{$module}/views/index.php"] = $this->viewModel($module);
            $files["sites/{$site}/application/{$module}/javascript/{$module}.js"] = "document.documentElement.dataset.opusModule='{$module}';\n";
            $files["sites/{$site}/application/{$module}/acl/policy.json"] = $this->json([
                'contract' => 'OPUS_MODULE_ACL_POLICY_V1',
                'resource' => $module,
                'default' => 'deny',
                'open' => ['anonymous', 'viewer', 'developer', 'admin'],
            ]);
            foreach (['fr', 'en', 'es'] as $locale) {
                $files["sites/{$site}/application/{$module}/local/{$locale}.json"] = $this->json(
                    $this->moduleCatalog($module, $locale)
                );
            }
        }

        foreach ($files as $path => $content) {
            $entries[] = ScaffoldEntry::file($path, $content);
        }
        return $entries;
    }

    /** @return array<string,mixed> */
    private function siteConfig(): array
    {
        return [
            'site_id' => $this->siteId,
            'site_name' => 'OPUS ' . $this->siteId,
            'role' => 'generated-opus-application',
            'contract' => 'OPUS_SITE_STANDARD_CONTRACT_CORE',
            'default_locale' => 'fr',
            'locales' => ['fr', 'en', 'es'],
            'theme' => 'starter',
            'application_root' => 'application',
            'default_root' => 'application/default',
            'application_fsm' => 'config/application.fsm.json',
            'dispatch_model' => 'fsm-module-first',
            'public_root' => 'www',
            'asset_root' => 'www/asset',
            'navigation' => ['fsm' => 'config/application.fsm.json'],
            'runtime' => [
                'contract' => 'OPUS_APPLICATION_SINGLETON_V1',
                'architecture' => 'singleton',
                'class' => $this->applicationClassName(),
                'file' => 'application/default/Application.php',
                'bootstrap' => 'application/default/bootstrap.php',
                'entrypoint' => 'www/index.php',
                'factory' => 'instance',
                'runner' => 'run',
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function routesConfig(): array
    {
        $routes = [];
        foreach (self::MODULES as $index => $module) {
            $routes[] = [
                'id' => $module . '.index',
                'path' => $module === 'home' ? '/' : '/' . $module,
                'state' => $module,
                'module' => $module,
                'action' => 'index',
                'template' => $module . '/templates/index.score',
                'view' => $module . '/views/index.php',
                'label' => 'menu.' . $module,
                'title_key' => 'page.title',
                'subtitle_key' => 'page.subtitle',
                'acl' => 'public',
                'fsm_state' => $module,
                'dispatch_action' => 'render_route',
                'show_in_menu' => true,
                'order' => ($index + 1) * 10,
            ];
        }
        return [
            'contract' => 'OPUS_ROUTE_REGISTRY_V1',
            'dispatch_model' => 'fsm-module-first',
            'routes' => $routes,
        ];
    }

    /** @return array<string,mixed> */
    private function menuConfig(): array
    {
        return [
            'contract' => 'OPUS_MENU_REGISTRY_V1',
            'items' => array_map(
                static fn (string $module): array => [
                    'route' => $module . '.index',
                    'label' => 'menu.' . $module,
                ],
                self::MODULES
            ),
        ];
    }

    /** @return array<string,mixed> */
    private function fsmConfig(): array
    {
        $states = [];
        $transitions = [];
        foreach (self::MODULES as $module) {
            $states[] = [
                'id' => $module,
                'module' => $module,
                'route' => $module === 'home' ? '/' : '/' . $module,
                'title_key' => 'menu.' . $module,
                'summary_key' => 'page.subtitle',
                'navigation' => ['label' => 'menu.' . $module],
            ];
            $transitions[] = [
                'id' => 'open.' . $module,
                'from' => '*',
                'event' => 'open_' . $module,
                'to' => $module,
                'guards' => ['route_exists'],
                'actions' => ['render_route'],
            ];
        }
        return [
            'contract' => 'OPUS_APPLICATION_FSM_V1',
            'site_id' => $this->siteId,
            'initial_state' => 'home',
            'states' => $states,
            'transitions' => $transitions,
        ];
    }

    /** @return array<string,mixed> */
    private function aclConfig(): array
    {
        return [
            'contract' => 'OPUS_GENERATED_APPLICATION_ACL_V1',
            'default' => 'deny',
            'policies' => [
                'public' => ['roles' => ['anonymous', 'viewer', 'developer', 'admin']],
                'authenticated' => ['roles' => ['viewer', 'developer', 'admin']],
                'administration' => ['roles' => ['developer', 'admin']],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function ssoConfig(): array
    {
        return [
            'contract' => 'OPUS_GENERATED_APPLICATION_SSO_V1',
            'session_name' => 'OPUS_' . strtoupper(str_replace('-', '_', $this->siteId)),
            'session_identity_key' => 'opus_identity',
            'providers' => [
                'session' => ['enabled' => true],
                'auth0-proxy' => [
                    'enabled' => true,
                    'trusted_proxy_addresses' => ['127.0.0.1', '::1'],
                    'proxy_secret_env' => 'OPUS_AUTH0_PROXY_SECRET',
                    'subject_header' => 'HTTP_X_OPUS_AUTH0_SUBJECT',
                    'roles_header' => 'HTTP_X_OPUS_AUTH0_ROLES',
                    'secret_header' => 'HTTP_X_OPUS_PROXY_SECRET',
                ],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function defaultCatalog(string $locale): array
    {
        $messages = [
            'fr' => ['language' => 'Langue', 'menu.home' => 'Accueil', 'menu.architecture' => 'Architecture', 'menu.router' => 'Routeur', 'menu.modules' => 'Modules', 'menu.controllers' => 'Contrôleurs', 'menu.views' => 'Vues', 'menu.models' => 'Modèles', 'menu.i18n' => 'Internationalisation', 'error.title' => 'Erreur OPUS', 'error.request_failed' => 'La requête a échoué.'],
            'en' => ['language' => 'Language', 'menu.home' => 'Home', 'menu.architecture' => 'Architecture', 'menu.router' => 'Router', 'menu.modules' => 'Modules', 'menu.controllers' => 'Controllers', 'menu.views' => 'Views', 'menu.models' => 'Models', 'menu.i18n' => 'Internationalization', 'error.title' => 'OPUS error', 'error.request_failed' => 'The request failed.'],
            'es' => ['language' => 'Idioma', 'menu.home' => 'Inicio', 'menu.architecture' => 'Arquitectura', 'menu.router' => 'Enrutador', 'menu.modules' => 'Módulos', 'menu.controllers' => 'Controladores', 'menu.views' => 'Vistas', 'menu.models' => 'Modelos', 'menu.i18n' => 'Internacionalización', 'error.title' => 'Error de OPUS', 'error.request_failed' => 'La solicitud ha fallado.'],
        ];
        return ['contract' => 'OPUS_I18N_CATALOG_V1', 'locale' => $locale, 'scope' => 'default', 'messages' => $messages[$locale]];
    }

    /** @return array<string,mixed> */
    private function moduleCatalog(string $module, string $locale): array
    {
        $subtitles = [
            'fr' => 'Module OPUS piloté par FSM : ' . $module,
            'en' => 'FSM-driven OPUS module: ' . $module,
            'es' => 'Módulo OPUS controlado por FSM: ' . $module,
        ];
        return [
            'contract' => 'OPUS_I18N_CATALOG_V1',
            'locale' => $locale,
            'scope' => $module,
            'messages' => [
                'page.title' => ucfirst($module),
                'page.subtitle' => $subtitles[$locale],
            ],
        ];
    }

    private function viewModel(string $module): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nreturn [\n    'module' => " . var_export($module, true) . ",\n    'page' => ['title' => '', 'subtitle' => ''],\n];\n";
    }

    private function layoutTemplate(): string
    {
        return "<!doctype html>\n<html lang=\"{{ lang }}\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>{{ page.title }}</title>\n{{{ assets.css }}}\n</head>\n<body class=\"opus-site\">\n{{{ common.header }}}\n<main id=\"main-content\" class=\"opus-shell\">{{{ content }}}</main>\n{{{ common.footer }}}\n{{{ assets.js }}}\n</body>\n</html>\n";
    }

    private function defaultCss(): string
    {
        return "body.opus-site{margin:0;font-family:system-ui,Segoe UI,Arial,sans-serif;background:#eef3f8;color:#162336}.opus-header,.opus-footer{background:#24466d;color:#fff;padding:24px}.opus-shell{padding:24px;min-height:60vh}.opus-card{display:block;margin:12px 0;padding:16px;background:#fff;border:1px solid #d7e0eb;border-radius:12px}.opus-error{border-color:#a22}.opus-menu a{color:#fff;margin-right:12px}.is-active{font-weight:700}\n";
    }

    private function applicationClass(): string
    {
        $class = $this->applicationClassName();
        $source = <<<'PHP'
<?php
declare(strict_types=1);

use Opus\Application\Runtime\GeneratedSiteRuntime;
use Opus\Http\Response;

final class {{APPLICATION_CLASS}}
{
    private static ?self $instance = null;
    private readonly GeneratedSiteRuntime $runtime;

    private function __construct(private readonly string $siteRoot)
    {
        $this->runtime = new GeneratedSiteRuntime($siteRoot);
    }

    public static function instance(string $siteRoot): self
    {
        $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');
        if (self::$instance instanceof self) {
            if (self::$instance->siteRoot !== $siteRoot) {
                throw new RuntimeException('OPUS_APPLICATION_SINGLETON_ROOT_MISMATCH');
            }
            return self::$instance;
        }
        return self::$instance = new self($siteRoot);
    }

    private function __clone()
    {
    }

    public function __wakeup(): void
    {
        throw new RuntimeException('OPUS_APPLICATION_SINGLETON_UNSERIALIZE_FORBIDDEN');
    }

    public function handle(): Response
    {
        return $this->runtime->handle();
    }

    public function run(): void
    {
        $this->handle()->send();
    }
}
PHP;
        return str_replace('{{APPLICATION_CLASS}}', $class, $source);
    }

    private function bootstrap(): string
    {
        $class = $this->applicationClassName();
        $source = <<<'PHP'
<?php
declare(strict_types=1);

$siteRoot = dirname(__DIR__, 2);
$opusRoot = dirname(dirname($siteRoot));
$autoload = $opusRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    exit;
}
require_once $autoload;
require_once __DIR__ . '/Application.php';

{{APPLICATION_CLASS}}::instance($siteRoot)->run();
PHP;
        return str_replace('{{APPLICATION_CLASS}}', $class, $source);
    }

    private function applicationClassName(): string
    {
        $parts = preg_split('/[^a-z0-9]+/i', $this->siteId) ?: [];
        $name = implode('', array_map(
            static fn (string $part): string => ucfirst(strtolower($part)),
            array_filter($parts, static fn (string $part): bool => $part !== '')
        ));
        if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \RuntimeException('OPUS_APPLICATION_CLASS_NAME_INVALID');
        }
        return $name . 'Application';
    }

    private function frontController(): string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/application/default/bootstrap.php';
PHP;
    }

    /** @param array<string,mixed> $data */
    private function json(array $data): string
    {
        return Json::instance()->encode($data, true) . "\n";
    }
}
