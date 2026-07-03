<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$sitesRoot = $root . DIRECTORY_SEPARATOR . 'sites';

function opus_fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function opus_path(string ...$parts): string
{
    return implode(DIRECTORY_SEPARATOR, array_filter($parts, static fn (string $part): bool => $part !== ''));
}

function opus_rel(string $path, string $root): string
{
    $normalizedPath = str_replace('\\', '/', $path);
    $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/') . '/';
    return str_starts_with($normalizedPath, $normalizedRoot) ? substr($normalizedPath, strlen($normalizedRoot)) : $normalizedPath;
}

function opus_ensure_dir(string $path): void
{
    if (is_dir($path)) {
        return;
    }
    if (file_exists($path)) {
        opus_fail('OPUS_MIGRATE_PATH_EXISTS_NOT_DIRECTORY: ' . $path);
    }
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        opus_fail('OPUS_MIGRATE_DIRECTORY_CREATE_FAILED: ' . $path);
    }
}

function opus_remove_empty_up(string $path, string $stop): void
{
    $path = rtrim($path, DIRECTORY_SEPARATOR);
    $stop = rtrim($stop, DIRECTORY_SEPARATOR);
    while ($path !== '' && str_replace('\\', '/', $path) !== str_replace('\\', '/', $stop) && is_dir($path)) {
        $items = array_values(array_diff(scandir($path) ?: [], ['.', '..']));
        if ($items !== []) {
            return;
        }
        rmdir($path);
        $path = dirname($path);
    }
}

function opus_files_identical(string $left, string $right): bool
{
    return is_file($left) && is_file($right) && filesize($left) === filesize($right) && hash_file('sha256', $left) === hash_file('sha256', $right);
}

function opus_move_file(string $from, string $to, string $siteRoot): void
{
    if (!is_file($from)) {
        return;
    }
    opus_ensure_dir(dirname($to));
    if (is_file($to)) {
        if (opus_files_identical($from, $to)) {
            unlink($from);
            opus_remove_empty_up(dirname($from), $siteRoot);
            return;
        }
        opus_fail('OPUS_MIGRATE_FILE_CONFLICT: ' . opus_rel($from, $siteRoot) . ' -> ' . opus_rel($to, $siteRoot));
    }
    if (file_exists($to)) {
        opus_fail('OPUS_MIGRATE_TARGET_EXISTS_NOT_FILE: ' . opus_rel($to, $siteRoot));
    }
    if (!rename($from, $to)) {
        if (!copy($from, $to)) {
            opus_fail('OPUS_MIGRATE_FILE_MOVE_FAILED: ' . opus_rel($from, $siteRoot));
        }
        unlink($from);
    }
    opus_remove_empty_up(dirname($from), $siteRoot);
}

function opus_move_tree_contents(string $from, string $to, string $siteRoot): void
{
    if (!is_dir($from)) {
        return;
    }
    opus_ensure_dir($to);
    $items = array_values(array_diff(scandir($from) ?: [], ['.', '..']));
    foreach ($items as $item) {
        $source = $from . DIRECTORY_SEPARATOR . $item;
        $target = $to . DIRECTORY_SEPARATOR . $item;
        if (is_dir($source)) {
            opus_move_tree_contents($source, $target, $siteRoot);
            opus_remove_empty_up($source, $siteRoot);
            continue;
        }
        opus_move_file($source, $target, $siteRoot);
    }
    opus_remove_empty_up($from, $siteRoot);
}

function opus_read_json_nullable(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        opus_fail('OPUS_MIGRATE_JSON_INVALID: ' . $path);
    }
    return $decoded;
}

function opus_write_json(string $path, array $data): void
{
    opus_ensure_dir(dirname($path));
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        opus_fail('OPUS_MIGRATE_JSON_ENCODE_FAILED: ' . $path);
    }
    file_put_contents($path, $json . "\n");
}

function opus_merge_json_file(string $from, string $to, string $siteRoot): void
{
    if (!is_file($from)) {
        return;
    }
    $source = opus_read_json_nullable($from) ?? [];
    $target = opus_read_json_nullable($to) ?? [];
    opus_write_json($to, array_replace($target, $source));
    unlink($from);
    opus_remove_empty_up(dirname($from), $siteRoot);
}

function opus_controller_dirs(string $siteRoot, string $controller): void
{
    foreach (['acl', 'helpers', 'css', 'javascript', 'local', 'local/fr', 'local/en', 'local/es', 'models', 'templates', 'views'] as $relative) {
        opus_ensure_dir(opus_path($siteRoot, 'application', $controller, str_replace('/', DIRECTORY_SEPARATOR, $relative)));
    }
}

function opus_normalize_controller(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'home';
    }
    $value = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $value) ?: $value;
    return strtolower(trim($value, '-_')) ?: 'home';
}

function opus_seed_default_files(string $siteRoot): void
{
    $defaultCss = opus_path($siteRoot, 'application', 'default', 'css', 'default.css');
    if (!is_file($defaultCss)) {
        file_put_contents($defaultCss, ":root{--opus-contract:OPUS_SITE_APPLICATION_TREE_V1_ETERNAL;}\n");
    }
    $defaultJs = opus_path($siteRoot, 'application', 'default', 'javascript', 'default.js');
    if (!is_file($defaultJs)) {
        file_put_contents($defaultJs, "document.documentElement.dataset.opusDefaultLayer='loaded';\n");
    }
    $layout = opus_path($siteRoot, 'application', 'default', 'templates', 'layout.score');
    if (!is_file($layout)) {
        file_put_contents($layout, "<!doctype html>\n<html lang=\"{{ lang }}\">\n<head>\n<meta charset=\"utf-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n<title>{{ page.title }}</title>\n{{{ assets.css }}}\n</head>\n<body>\n{{{ common.header }}}\n{{{ content }}}\n{{{ common.footer }}}\n{{{ assets.js }}}\n</body>\n</html>\n");
    } else {
        $content = (string) file_get_contents($layout);
        if (!str_contains($content, '{{{ assets.css }}}')) {
            $content = str_replace('</head>', "{{{ assets.css }}}\n</head>", $content);
        }
        if (!str_contains($content, '{{{ assets.js }}}')) {
            $content = str_replace('</body>', "{{{ assets.js }}}\n</body>", $content);
        }
        file_put_contents($layout, $content);
    }
    foreach (['fr', 'en', 'es'] as $locale) {
        $path = opus_path($siteRoot, 'application', 'default', 'local', $locale, 'i18n.json');
        if (!is_file($path)) {
            opus_write_json($path, ['language' => strtoupper($locale), 'menu.home' => 'Home']);
        }
    }
    $themeCss = opus_path($siteRoot, 'www', 'asset', 'themes', 'starter', 'css', 'theme.css');
    if (!is_file($themeCss)) {
        file_put_contents($themeCss, "body{--opus-theme:starter;}\n");
    }
    $themeJs = opus_path($siteRoot, 'www', 'asset', 'themes', 'starter', 'js', 'theme.js');
    if (!is_file($themeJs)) {
        file_put_contents($themeJs, "document.documentElement.dataset.opusThemeLayer='starter';\n");
    }
}

function opus_update_site_config(string $siteRoot, string $siteName): void
{
    $path = opus_path($siteRoot, 'config', 'site.json');
    $config = opus_read_json_nullable($path) ?? [];
    $config['site_id'] = $config['site_id'] ?? $siteName;
    $config['site_name'] = $config['site_name'] ?? ('OPUS ' . $siteName);
    $config['contract'] = 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL';
    $config['starter_contract'] = $config['starter_contract'] ?? 'OPUS_ASAP_INHERITANCE_STARTER_V1';
    $config['default_locale'] = $config['default_locale'] ?? 'fr';
    $config['locales'] = array_values(array_unique(array_merge(['fr', 'en', 'es'], array_filter((array) ($config['locales'] ?? []), 'is_scalar'))));
    $config['theme'] = $config['theme'] ?? 'starter';
    $config['application_root'] = 'application';
    $config['default_root'] = 'application/default';
    $config['controller_root_pattern'] = 'application/<controller>';
    $config['public_root'] = 'www';
    $config['asset_root'] = 'www/asset';
    $config['theme_root_pattern'] = 'www/asset/themes/<theme>';
    $config['css_inheritance'] = ['application/default/css', 'www/asset/themes/<theme>/css', 'application/<controller>/css'];
    $config['js_inheritance'] = ['application/default/javascript', 'www/asset/themes/<theme>/js', 'application/<controller>/javascript'];
    $config['home_route'] = $config['home_route'] ?? 'home.index';
    opus_write_json($path, $config);
}

function opus_update_projection_source(string $path): void
{
    $data = opus_read_json_nullable($path);
    if ($data === null) {
        return;
    }
    if (($data['source'] ?? null) === 'application/config/routes.json') {
        $data['source'] = 'config/routes.json';
    }
    opus_write_json($path, $data);
}

function opus_migrate_routes_and_pages(string $siteRoot): array
{
    $controllers = [];
    $routesPath = opus_path($siteRoot, 'config', 'routes.json');
    $routes = opus_read_json_nullable($routesPath);
    if (is_array($routes) && isset($routes['routes']) && is_array($routes['routes'])) {
        foreach ($routes['routes'] as $index => $route) {
            if (!is_array($route)) {
                continue;
            }
            $controller = opus_normalize_controller((string) ($route['controller'] ?? $route['page'] ?? strtok((string) ($route['id'] ?? ''), '.') ?: 'home'));
            $routes['routes'][$index]['controller'] = $controller;
            $routes['routes'][$index]['page'] = $routes['routes'][$index]['page'] ?? $controller;
            $template = str_replace('\\', '/', (string) ($route['template'] ?? ''));
            if ($template === '' || preg_match('#^application/pages/([^/]+)\.score$#', $template, $match)) {
                $page = isset($match[1]) ? opus_normalize_controller($match[1]) : $controller;
                $old = opus_path($siteRoot, 'application', 'pages', $page . '.score');
                $new = opus_path($siteRoot, 'application', $controller, 'templates', 'index.score');
                opus_move_file($old, $new, $siteRoot);
                $routes['routes'][$index]['template'] = 'application/' . $controller . '/templates/index.score';
            }
            $controllers[$controller] = true;
        }
        opus_write_json($routesPath, $routes);
    }

    $pagesDir = opus_path($siteRoot, 'application', 'pages');
    if (is_dir($pagesDir)) {
        foreach (glob($pagesDir . DIRECTORY_SEPARATOR . '*.score') ?: [] as $file) {
            $controller = opus_normalize_controller(pathinfo($file, PATHINFO_FILENAME));
            opus_move_file($file, opus_path($siteRoot, 'application', $controller, 'templates', 'index.score'), $siteRoot);
            $controllers[$controller] = true;
        }
        opus_remove_empty_up($pagesDir, $siteRoot);
    }

    $applicationDir = opus_path($siteRoot, 'application');
    foreach (glob($applicationDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $dir) {
        $name = basename($dir);
        if (!in_array($name, ['default', 'common', 'config', 'pages'], true)) {
            $controllers[opus_normalize_controller($name)] = true;
        }
    }

    if ($controllers === []) {
        $controllers['home'] = true;
    }

    foreach (array_keys($controllers) as $controller) {
        opus_controller_dirs($siteRoot, $controller);
        $controllerI18n = opus_path($siteRoot, 'application', $controller, 'local', 'fr', 'i18n.json');
        if (!is_file($controllerI18n)) {
            opus_write_json($controllerI18n, ['page.title' => ucfirst($controller), 'page.kicker' => strtoupper($controller), 'page.subtitle' => 'OPUS']);
        }
        $template = opus_path($siteRoot, 'application', $controller, 'templates', 'index.score');
        if (!is_file($template)) {
            file_put_contents($template, "<article><h1>{{ page.title }}</h1><p>{{ page.subtitle }}</p></article>\n");
        }
    }

    return array_keys($controllers);
}

function opus_validate_no_forbidden_dirs(string $siteRoot): void
{
    foreach (['public', 'resources', 'application/common', 'application/pages', 'application/config'] as $relative) {
        $path = opus_path($siteRoot, str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if (is_dir($path)) {
            opus_remove_empty_up($path, $siteRoot);
        }
        if (is_dir($path)) {
            $items = array_values(array_diff(scandir($path) ?: [], ['.', '..']));
            if ($items !== []) {
                opus_fail('OPUS_MIGRATE_FORBIDDEN_DIRECTORY_NOT_EMPTY: ' . opus_rel($path, $siteRoot));
            }
        }
    }
}

function opus_migrate_site(string $siteRoot): void
{
    $siteName = basename($siteRoot);
    opus_ensure_dir(opus_path($siteRoot, 'application'));
    opus_ensure_dir(opus_path($siteRoot, 'application', 'default'));
    foreach (['acl', 'helpers', 'css', 'javascript', 'local', 'local/fr', 'local/en', 'local/es', 'models', 'templates', 'templates/components', 'views'] as $relative) {
        opus_ensure_dir(opus_path($siteRoot, 'application', 'default', str_replace('/', DIRECTORY_SEPARATOR, $relative)));
    }
    foreach (['config', 'www', 'www/asset', 'www/asset/css', 'www/asset/js', 'www/asset/img', 'www/asset/themes', 'www/asset/themes/starter', 'www/asset/themes/starter/css', 'www/asset/themes/starter/js', 'www/asset/themes/starter/img'] as $relative) {
        opus_ensure_dir(opus_path($siteRoot, str_replace('/', DIRECTORY_SEPARATOR, $relative)));
    }

    opus_move_tree_contents(opus_path($siteRoot, 'application', 'config'), opus_path($siteRoot, 'config'), $siteRoot);
    opus_move_tree_contents(opus_path($siteRoot, 'application', 'common'), opus_path($siteRoot, 'application', 'default'), $siteRoot);
    opus_move_tree_contents(opus_path($siteRoot, 'public', 'assets'), opus_path($siteRoot, 'www', 'asset'), $siteRoot);
    opus_move_tree_contents(opus_path($siteRoot, 'public'), opus_path($siteRoot, 'www'), $siteRoot);
    opus_move_tree_contents(opus_path($siteRoot, 'resources', 'assets'), opus_path($siteRoot, 'www', 'asset'), $siteRoot);
    opus_move_tree_contents(opus_path($siteRoot, 'resources', 'themes'), opus_path($siteRoot, 'www', 'asset', 'themes'), $siteRoot);

    $i18nRoot = opus_path($siteRoot, 'resources', 'i18n');
    if (is_dir($i18nRoot)) {
        foreach (glob($i18nRoot . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
            $locale = opus_normalize_controller(pathinfo($file, PATHINFO_FILENAME));
            opus_merge_json_file($file, opus_path($siteRoot, 'application', 'default', 'local', $locale, 'i18n.json'), $siteRoot);
        }
        opus_remove_empty_up($i18nRoot, $siteRoot);
    }

    opus_update_site_config($siteRoot, $siteName);
    opus_update_projection_source(opus_path($siteRoot, 'config', 'menu.json'));
    opus_update_projection_source(opus_path($siteRoot, 'config', 'rubrics.json'));
    opus_migrate_routes_and_pages($siteRoot);
    opus_seed_default_files($siteRoot);
    opus_validate_no_forbidden_dirs($siteRoot);
}

if (!is_dir($sitesRoot)) {
    echo "OPUS_MIGRATE_NO_SITES_DIRECTORY\n";
    exit(0);
}

$sites = array_values(array_filter(glob($sitesRoot . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [], static fn (string $path): bool => !str_starts_with(basename($path), '.')));
foreach ($sites as $siteRoot) {
    opus_migrate_site($siteRoot);
    echo 'OPUS_SITE_MIGRATED: ' . basename($siteRoot) . "\n";
}

echo "OPUS_SITES_ETERNAL_CONTRACT_MIGRATION_OK\n";
