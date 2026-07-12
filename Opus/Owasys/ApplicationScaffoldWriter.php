<?php
declare(strict_types=1);

namespace Opus\Owasys;

use RuntimeException;

/**
 * Writes a validated OWASYS scaffold plan to disk.
 *
 * Safety contract:
 * - default caller mode should be dry-run;
 * - actual write refuses an existing target root;
 * - every output path must stay below the scaffold site root;
 * - public, src and resources roots remain forbidden.
 */
final class ApplicationScaffoldWriter
{
    private const SITE_CONTRACT = 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL';
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

        foreach (['config', 'application/default', 'www', 'www/index.php', 'www/asset'] as $required) {
            $needle = $siteRoot . '/' . $required;
            if ($required === 'www/index.php') {
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
        $controller = $this->controllerFromPath($path);

        if (str_ends_with($path, '/config/site.json')) {
            return $this->json($this->siteConfig($plan));
        }
        if (str_ends_with($path, '/config/routes.json')) {
            return $this->json($this->routesConfig($plan));
        }
        if (str_ends_with($path, '/config/menu.json')) {
            return $this->json($this->menuConfig($plan));
        }
        if (str_ends_with($path, '/config/fsm.json')) {
            return $this->json($this->fsmConfig($plan));
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
            return "body.opus-generated-site{margin:0;font-family:system-ui,Segoe UI,Arial,sans-serif;background:#eef3f8;color:#162336}.opus-header,.opus-footer{background:#24466d;color:#fff;padding:24px}.opus-shell{padding:24px;min-height:60vh}.opus-card{display:block;margin:12px 0;padding:16px;background:#fff;border:1px solid #d7e0eb;border-radius:12px}\n";
        }
        if (str_ends_with($path, '/application/default/javascript/default.js')) {
            return "document.documentElement.dataset.opusDefaultLayer='loaded';\n";
        }
        if (str_ends_with($path, '/www/index.php')) {
            return $this->frontController();
        }
        if (str_ends_with($path, '/theme.css')) {
            return "body.opus-generated-site{--opus-theme:" . $plan['theme'] . "}\n";
        }
        if (str_ends_with($path, '/theme.js')) {
            return "document.documentElement.dataset.opusThemeLayer='" . $plan['theme'] . "';\n";
        }
        if ($controller !== null && str_ends_with($path, '/templates/index.score')) {
            return "<section class=\"opus-card\"><h2>{{ page.title }}</h2><p>{{ page.subtitle }}</p></section>\n";
        }
        if ($controller !== null && str_ends_with($path, '/views/index.php')) {
            return $this->viewModel($controller);
        }
        if ($controller !== null && str_ends_with($path, '/' . $controller . '.css')) {
            return "/* " . $controller . " */\n";
        }
        if ($controller !== null && str_ends_with($path, '/' . $controller . '.js')) {
            return "document.documentElement.dataset.opusControllerLayer='" . $controller . "';\n";
        }
        if (str_ends_with($path, '/i18n.json')) {
            return $this->json(['page.title' => $controller ?? 'default', 'page.subtitle' => 'Generated by OWASYS']);
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
            'src_directory_allowed' => false,
            'css_inheritance' => ['application/default/css', 'www/asset/themes/<theme>/css', 'application/<controller>/css'],
            'js_inheritance' => ['application/default/javascript', 'www/asset/themes/<theme>/js', 'application/<controller>/javascript'],
            'generated_by' => 'owasys',
        ];
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private function routesConfig(array $plan): array
    {
        $routes = [];
        foreach ($plan['routes'] as $index => $route) {
            $controller = (string) ($route['controller'] ?? 'home');
            $path = (string) ($route['path'] ?? ($controller === 'home' ? '/' : '/' . $controller));
            $routes[] = [
                'id' => (string) ($route['id'] ?? $controller . '.index'),
                'path' => $path,
                'controller' => $controller,
                'class' => null,
                'template' => 'application/' . $controller . '/templates/index.score',
                'view' => 'application/' . $controller . '/views/index.php',
                'label' => 'menu.' . $controller,
                'show_in_menu' => true,
                'order' => ($index + 1) * 10,
            ];
        }
        return ['contract' => 'OPUS_ROUTE_REGISTRY_V1', 'routes' => $routes];
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private function menuConfig(array $plan): array
    {
        return [
            'contract' => 'OPUS_MENU_ROUTE_PROJECTION_V1',
            'items' => array_map(static fn (string $controller): array => ['route' => $controller . '.index', 'controller' => $controller, 'label' => 'menu.' . $controller], $plan['controllers']),
        ];
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private function fsmConfig(array $plan): array
    {
        return [
            'contract' => 'OPUS_FSM_REGISTRY_V1',
            'initial_state' => 'HOME',
            'states' => array_map(static fn (string $controller): array => ['id' => strtoupper(str_replace('-', '_', $controller)), 'controller' => $controller], $plan['controllers']),
            'transitions' => [],
        ];
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private function rubricsConfig(array $plan): array
    {
        $rubrics = [];
        foreach ($plan['controllers'] as $controller) {
            if ($controller === 'home') {
                continue;
            }
            $rubrics[] = ['controller' => $controller, 'route' => $controller . '.index'];
        }
        return ['contract' => 'OPUS_HOME_DEMO_CARD_ROUTE_PROJECTION_V1', 'rubrics' => $rubrics];
    }

    private function viewModel(string $controller): string
    {
        $title = ucfirst(str_replace('-', ' ', $controller));
        return "<?php\ndeclare(strict_types=1);\n\nreturn [\n    'title' => " . var_export($title, true) . ",\n    'subtitle' => 'Generated by OWASYS',\n];\n";
    }

    private function frontController(): string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);

$siteRoot = dirname(__DIR__);
$routesFile = $siteRoot . '/config/routes.json';
$routesConfig = json_decode((string) file_get_contents($routesFile), true);
if (!is_array($routesConfig) || !isset($routesConfig['routes']) || !is_array($routesConfig['routes'])) {
    http_response_code(500);
    echo 'OPUS_GENERATED_ROUTES_INVALID';
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
    echo 'OPUS_GENERATED_ROUTE_NOT_FOUND';
    exit;
}

$controller = (string) ($route['controller'] ?? 'home');
if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $controller)) {
    http_response_code(500);
    echo 'OPUS_GENERATED_CONTROLLER_INVALID';
    exit;
}

$viewFile = $siteRoot . '/application/' . $controller . '/views/index.php';
$page = is_file($viewFile) ? require $viewFile : ['title' => $controller, 'subtitle' => 'Generated by OWASYS'];
if (!is_array($page)) {
    $page = ['title' => $controller, 'subtitle' => 'Generated by OWASYS'];
}

$h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $h((string) ($page['title'] ?? $controller)) . '</title><link rel="stylesheet" href="/asset/css/default.css"><link rel="stylesheet" href="/asset/themes/starter/css/theme.css"></head><body class="opus-generated-site"><main class="opus-shell"><article class="opus-card"><h1>' . $h((string) ($page['title'] ?? $controller)) . '</h1><p>' . $h((string) ($page['subtitle'] ?? 'Generated by OWASYS')) . '</p></article></main><script src="/asset/js/default.js"></script><script src="/asset/themes/starter/js/theme.js"></script></body></html>';
PHP;
    }

    private function controllerFromPath(string $path): ?string
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
