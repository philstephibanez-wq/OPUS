<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$sitesRoot = $root . DIRECTORY_SEPARATOR . 'sites';

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function path_join(string ...$parts): string
{
    return implode(DIRECTORY_SEPARATOR, array_filter($parts, static fn (string $part): bool => $part !== ''));
}

function mkdirp(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        fail('OPUS_MKDIR_FAILED: ' . $dir);
    }
}

function rrmdir(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    $items = scandir($path);
    if ($items === false) {
        fail('OPUS_SCANDIR_FAILED: ' . $path);
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        rrmdir($path . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($path);
}

function copy_file(string $source, string $target): void
{
    mkdirp(dirname($target));
    if (!copy($source, $target)) {
        fail('OPUS_COPY_FAILED: ' . $source . ' -> ' . $target);
    }
}

function write_file(string $path, string $content): void
{
    mkdirp(dirname($path));
    if (file_put_contents($path, $content) === false) {
        fail('OPUS_WRITE_FAILED: ' . $path);
    }
}

function json_file(string $path, array $data): void
{
    write_file($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
}

function slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/\.php$/', '', $value) ?? $value;
    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? $value;
    $value = trim($value, '-_');
    return $value !== '' && $value !== 'index' ? $value : 'home';
}

function clean_php_file(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $s = file_get_contents($path);
    if ($s === false) {
        fail('OPUS_READ_FAILED: ' . $path);
    }
    $s = preg_replace('/^\xEF\xBB\xBF/', '', $s) ?? $s;
    $pos = strpos($s, '<?php');
    if ($pos !== false && $pos > 0) {
        $s = substr($s, $pos);
    }
    write_file($path, $s);
}

function default_i18n(string $siteName): array
{
    return [
        'language' => 'Langue',
        'menu.home' => 'Accueil',
        'page.title' => $siteName,
        'page.subtitle' => 'Site migré vers le contrat OPUS/ASAP éternel',
    ];
}

function legacy_front_controller(): string
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
if (!is_file($legacy)) {
    http_response_code(404);
    echo 'OPUS_ROUTE_NOT_FOUND: ' . htmlspecialchars($controller, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    exit;
}
require $legacy;
PHP;
}

function ensure_controller_tree(string $siteDir, string $controller): void
{
    foreach (['acl', 'helpers', 'css', 'javascript', 'local/fr', 'local/en', 'local/es', 'models', 'templates', 'views'] as $sub) {
        mkdirp(path_join($siteDir, 'application', $controller, $sub));
    }
    write_file(path_join($siteDir, 'application', $controller, 'templates', 'index.score'), '<section class="opus-card"><h2>{{ page.title }}</h2><p>{{ page.subtitle }}</p></section>' . "\n");
    write_file(path_join($siteDir, 'application', $controller, 'css', $controller . '.css'), '/* ' . $controller . ' */' . "\n");
    write_file(path_join($siteDir, 'application', $controller, 'javascript', $controller . '.js'), "document.documentElement.dataset.opusControllerLayer='" . addslashes($controller) . "';\n");
    foreach (['fr', 'en', 'es'] as $locale) {
        json_file(path_join($siteDir, 'application', $controller, 'local', $locale, 'i18n.json'), [
            'page.title' => strtoupper($controller),
            'page.subtitle' => 'Controller OPUS/ASAP: ' . $controller,
            'menu.' . $controller => $controller,
        ]);
    }
}

if (!is_dir($sitesRoot)) {
    fail('OPUS_SITES_ROOT_MISSING');
}

foreach (glob($sitesRoot . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $siteDir) {
    $siteId = basename($siteDir);
    $publicDir = path_join($siteDir, 'public');
    $wwwDir = path_join($siteDir, 'www');
    $configDir = path_join($siteDir, 'config');
    $applicationDir = path_join($siteDir, 'application');

    $legacyPublicFiles = [];
    if (is_dir($publicDir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($publicDir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $legacyPublicFiles[] = $file->getPathname();
            }
        }
    }

    rrmdir($applicationDir);
    rrmdir($configDir);
    rrmdir($wwwDir);

    foreach (['default/acl', 'default/helpers', 'default/css', 'default/javascript', 'default/local/fr', 'default/local/en', 'default/local/es', 'default/models', 'default/templates/components', 'default/views'] as $sub) {
        mkdirp(path_join($applicationDir, $sub));
    }
    foreach (['asset/css', 'asset/js', 'asset/themes/starter/css', 'asset/themes/starter/js', 'asset/themes/starter/img'] as $sub) {
        mkdirp(path_join($wwwDir, $sub));
    }

    write_file(path_join($applicationDir, 'default', 'templates', 'layout.score'), "<!doctype html>\n<html lang=\"{{ lang }}\"><head><meta charset=\"utf-8\"><title>{{ page.title }}</title>{{{ assets.css }}}</head><body class=\"opus-asap-site\">{{{ common.header }}}<main>{{{ content }}}</main>{{{ common.footer }}}{{{ assets.js }}}</body></html>\n");
    write_file(path_join($applicationDir, 'default', 'templates', 'components', 'header.score'), "<header><h1>{{ site.name }}</h1>{{{ common.menu }}}</header>\n");
    write_file(path_join($applicationDir, 'default', 'templates', 'components', 'footer.score'), "<footer>{{ site.contract }}</footer>\n");
    write_file(path_join($applicationDir, 'default', 'templates', 'components', 'menu-item.score'), "<a href=\"{{ menu_item.path }}\">{{ menu_item.label }}</a>\n");
    write_file(path_join($applicationDir, 'default', 'css', 'default.css'), "body{font-family:system-ui,Segoe UI,Arial,sans-serif}.opus-card{padding:1rem;border:1px solid #ddd}\n");
    write_file(path_join($applicationDir, 'default', 'javascript', 'default.js'), "document.documentElement.dataset.opusDefaultLayer='loaded';\n");
    write_file(path_join($wwwDir, 'asset', 'themes', 'starter', 'css', 'theme.css'), "body{--opus-theme:starter}\n");
    write_file(path_join($wwwDir, 'asset', 'themes', 'starter', 'js', 'theme.js'), "document.documentElement.dataset.opusThemeLayer='starter';\n");
    write_file(path_join($wwwDir, 'index.php'), legacy_front_controller());

    foreach (['fr', 'en', 'es'] as $locale) {
        json_file(path_join($applicationDir, 'default', 'local', $locale, 'i18n.json'), default_i18n($siteId));
    }

    $controllers = ['home'];
    foreach ($legacyPublicFiles as $source) {
        $relative = str_replace('\\', '/', substr($source, strlen($publicDir) + 1));
        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if ($extension === 'php') {
            $controller = slug(pathinfo($source, PATHINFO_FILENAME));
            if (!in_array($controller, $controllers, true)) {
                $controllers[] = $controller;
            }
            ensure_controller_tree($siteDir, $controller);
            copy_file($source, path_join($applicationDir, $controller, 'views', 'legacy-public-entry.php'));
            continue;
        }
        if ($extension === 'css') {
            copy_file($source, path_join($wwwDir, 'asset', 'css', basename($source)));
            continue;
        }
        if ($extension === 'js') {
            copy_file($source, path_join($wwwDir, 'asset', 'js', basename($source)));
            continue;
        }
        copy_file($source, path_join($wwwDir, 'asset', $relative));
    }

    foreach ($controllers as $controller) {
        ensure_controller_tree($siteDir, $controller);
    }

    $routes = [];
    foreach (array_values($controllers) as $index => $controller) {
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

    json_file(path_join($configDir, 'site.json'), [
        'site_id' => $siteId,
        'site_name' => 'OPUS ' . $siteId,
        'contract' => 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
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
    ]);
    json_file(path_join($configDir, 'routes.json'), ['contract' => 'OPUS_ROUTE_REGISTRY_V1', 'routes' => $routes]);
    json_file(path_join($configDir, 'menu.json'), ['contract' => 'OPUS_MENU_ROUTE_PROJECTION_V1', 'items' => array_map(static fn (array $route): array => ['route' => $route['id'], 'controller' => $route['controller'], 'label' => $route['label'], 'order' => $route['order']], $routes)]);
    json_file(path_join($configDir, 'fsm.json'), ['contract' => 'OPUS_FSM_REGISTRY_V1', 'initial_state' => 'HOME', 'states' => array_map(static fn (array $route): array => ['id' => $route['fsm_state'], 'controller' => $route['controller']], $routes), 'transitions' => []]);
    json_file(path_join($configDir, 'rubrics.json'), ['contract' => 'OPUS_HOME_DEMO_CARD_ROUTE_PROJECTION_V1', 'rubrics' => array_values(array_filter(array_map(static fn (array $route): array => ['route' => $route['id'], 'controller' => $route['controller'], 'order' => $route['order']], $routes), static fn (array $item): bool => $item['controller'] !== 'home'))]);

    rrmdir($publicDir);
    echo 'OPUS_SITE_FULLY_REORGANIZED: ' . $siteId . ' controllers=' . count($controllers) . "\n";
}

foreach (['Opus/Scaffold/SiteScaffoldPlan.php', 'bin/opus', 'tools/smoke_opus_site_contract_eternal.php'] as $relative) {
    clean_php_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
}

echo "OPUS_SITES_FULL_REORG_OK\n";
