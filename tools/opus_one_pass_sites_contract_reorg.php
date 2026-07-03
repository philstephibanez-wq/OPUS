<?php
declare(strict_types=1);

$root = dirname(__DIR__);

function opus_write_text(string $path, string $content): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        fwrite(STDERR, "OPUS_WRITE_DIR_FAILED: {$dir}\n");
        exit(1);
    }
    if (file_put_contents($path, $content) === false) {
        fwrite(STDERR, "OPUS_WRITE_FAILED: {$path}\n");
        exit(1);
    }
}

function opus_json_write(string $path, array $data): void
{
    opus_write_text($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
}

function opus_read_json(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function opus_mkdir(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
        fwrite(STDERR, "OPUS_MKDIR_FAILED: {$path}\n");
        exit(1);
    }
}

function opus_copy_recursive(string $source, string $target): void
{
    if (!is_dir($source)) {
        return;
    }
    opus_mkdir($target);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $dest = $target . DIRECTORY_SEPARATOR . $relative;
        if ($item->isDir()) {
            opus_mkdir($dest);
            continue;
        }
        opus_mkdir(dirname($dest));
        copy($item->getPathname(), $dest);
    }
}

function opus_remove_recursive(string $path): void
{
    if (!is_dir($path)) {
        if (is_file($path)) {
            unlink($path);
        }
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($path);
}

function opus_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/\.php$/', '', $value) ?? $value;
    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? $value;
    $value = trim($value, '-_');
    return $value !== '' ? $value : 'home';
}

function opus_title(string $slug): string
{
    return ucwords(str_replace(['-', '_'], ' ', $slug));
}

function opus_fix_php_file(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $text = (string) file_get_contents($path);
    $pos = strpos($text, '<?php');
    if ($pos !== false && $pos > 0) {
        $text = substr($text, $pos);
    }
    opus_write_text($path, $text);
}

function opus_ensure_scaffold_roots(string $root): void
{
    $path = $root . '/Opus/Scaffold/SiteScaffoldPlan.php';
    if (!is_file($path)) {
        return;
    }
    opus_fix_php_file($path);
    $text = (string) file_get_contents($path);

    $pairs = [
        '            "sites/{$site}/application/default/acl",' => [
            '            "sites/{$site}/application",',
            '            "sites/{$site}/application/default",',
        ],
        '            "sites/{$site}/application/default/local/fr",' => [
            '            "sites/{$site}/application/default/local",',
        ],
        '            "sites/{$site}/application/default/templates/components",' => [
            '            "sites/{$site}/application/default/templates",',
        ],
        '            "sites/{$site}/www/asset/css",' => [
            '            "sites/{$site}/www",',
            '            "sites/{$site}/www/asset",',
        ],
        '            "sites/{$site}/www/asset/themes/starter/css",' => [
            '            "sites/{$site}/www/asset/themes",',
            '            "sites/{$site}/www/asset/themes/starter",',
        ],
        '            "{$controller}/acl",' => [
            '            "{$controller}",',
        ],
        '            "{$controller}/local/fr",' => [
            '            "{$controller}/local",',
        ],
    ];

    foreach ($pairs as $anchor => $insertions) {
        if (!str_contains($text, $anchor)) {
            continue;
        }
        $missing = [];
        foreach ($insertions as $insertion) {
            if (!str_contains($text, $insertion)) {
                $missing[] = $insertion;
            }
        }
        if ($missing !== []) {
            $text = str_replace($anchor, implode("\n", $missing) . "\n" . $anchor, $text);
        }
    }

    opus_write_text($path, $text);
}

function opus_ensure_bin_contract(string $root): void
{
    $path = $root . '/bin/opus';
    if (!is_file($path)) {
        return;
    }
    opus_fix_php_file($path);
    $text = (string) file_get_contents($path);
    $text = str_replace("return $siteRoot . DIRECTORY_SEPARATOR . 'public';", "return $siteRoot . DIRECTORY_SEPARATOR . 'www';", $text);
    $old = "$required = ['application', 'application/config', 'resources/i18n', 'public', 'public/index.php'];";
    $new = "$required = ['config', 'config/site.json', 'config/routes.json', 'application', 'application/default', 'application/default/acl', 'application/default/helpers', 'application/default/css', 'application/default/javascript', 'application/default/local', 'application/default/models', 'application/default/templates', 'application/default/views', 'www', 'www/index.php', 'www/asset', 'www/asset/css', 'www/asset/js', 'www/asset/themes'];";
    $text = str_replace($old, $new, $text);
    $text = str_replace('OPUS_SITE_PUBLIC_FRONT_CONTROLLER_MISSING', 'OPUS_SITE_WWW_FRONT_CONTROLLER_MISSING', $text);
    opus_write_text($path, $text);
}

function opus_template_layout(): string
{
    return <<<'SCORE'
<!doctype html>
<html lang="{{ lang }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ page.title }}</title>
  {{{ assets.css }}}
</head>
<body class="opus-asap-site">
  {{{ common.header }}}
  <main class="opus-shell" id="main-content">
    {{{ content }}}
  </main>
  {{{ common.footer }}}
  {{{ assets.js }}}
</body>
</html>
SCORE;
}

function opus_template_header(): string
{
    return <<<'SCORE'
<header class="opus-header">
  <div class="opus-header__inner">
    <div>
      <p class="opus-kicker">OPUS / ASAP</p>
      <h1>{{ site.name }}</h1>
    </div>
    {{{ common.language_selector }}}
  </div>
  <nav class="opus-nav" aria-label="Navigation principale">
    {{{ common.menu }}}
  </nav>
</header>
SCORE;
}

function opus_template_footer(): string
{
    return <<<'SCORE'
<footer class="opus-footer">
  <div class="opus-footer__inner">
    <span>{{ site.framework }}</span>
    <span>{{ site.contract }}</span>
  </div>
</footer>
SCORE;
}

function opus_template_menu_item(): string
{
    return '<a class="opus-nav__link {{ menu_item.active_class }}" href="{{ menu_item.path }}">{{ menu_item.label }}</a>' . "\n";
}

function opus_template_language_selector(): string
{
    return <<<'SCORE'
<form class="opus-lang" method="get">
  <select name="lang" onchange="this.form.submit()">
    {{{ common.language_options }}}
  </select>
</form>
SCORE;
}

function opus_template_rubric_card(): string
{
    return <<<'SCORE'
<a class="opus-card" href="{{ rubric.path }}">
  <span>{{ rubric.kicker }}</span>
  <strong>{{ rubric.title }}</strong>
  <p>{{ rubric.description }}</p>
</a>
SCORE;
}

function opus_template_page(string $controller): string
{
    return <<<'SCORE'
<section class="opus-page">
  <p class="opus-kicker">{{ page.kicker }}</p>
  <h2>{{ page.title }}</h2>
  <p class="opus-lead">{{ page.subtitle }}</p>
  <section class="opus-panel">
    <h3>{{ page.section_title }}</h3>
    <p>{{ page.section_intro }}</p>
  </section>
</section>
SCORE;
}

function opus_template_home(): string
{
    return <<<'SCORE'
<section class="opus-hero">
  <p class="opus-kicker">{{ page.kicker }}</p>
  <h2>{{ page.title }}</h2>
  <p>{{ page.subtitle }}</p>
</section>
<section class="opus-grid">
  {{{ home.rubric_cards }}}
</section>
SCORE;
}

function opus_default_css(): string
{
    return <<<'CSS'
:root{--opus-blue:#274f7c;--opus-bg:#eef3f8;--opus-text:#142033;--opus-card:#fff;--opus-border:#d7e0eb}*{box-sizing:border-box}body.opus-asap-site{margin:0;background:linear-gradient(135deg,#182433,#edf3fa 34%,#edf3fa);color:var(--opus-text);font:16px/1.5 system-ui,-apple-system,Segoe UI,Arial,sans-serif}.opus-shell,.opus-header__inner,.opus-nav,.opus-footer__inner{width:min(1180px,calc(100% - 32px));margin:0 auto}.opus-header{margin:42px auto 0;width:min(1180px,calc(100% - 32px));border-radius:24px 24px 0 0;overflow:hidden;background:#253a57;color:#fff}.opus-header__inner{min-height:130px;display:flex;align-items:center;justify-content:space-between}.opus-header h1{margin:0;font-size:clamp(2rem,5vw,4rem)}.opus-kicker{margin:0 0 10px;text-transform:uppercase;letter-spacing:.16em;font-weight:800;color:#6aa6e8}.opus-nav{display:flex;gap:4px;flex-wrap:wrap;background:#3379b4}.opus-nav__link{color:#fff;text-decoration:none;padding:16px 18px;font-weight:800}.opus-nav__link--active,.opus-nav__link:hover{background:rgba(0,0,0,.18)}.opus-shell{min-height:520px;background:#f7f9fc;padding:34px 34px 80px}.opus-hero,.opus-page,.opus-panel,.opus-card{background:var(--opus-card);border:1px solid var(--opus-border);border-radius:18px;padding:24px}.opus-hero{margin-bottom:22px}.opus-hero h2,.opus-page h2{margin:0 0 12px;font-size:clamp(2rem,4vw,3rem)}.opus-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:18px}.opus-card{display:block;color:inherit;text-decoration:none}.opus-card strong{display:block;margin:8px 0;color:#0b62d6}.opus-footer{width:min(1180px,calc(100% - 32px));margin:0 auto 40px;background:#1b2b43;color:#dce9fa;border-radius:0 0 24px 24px}.opus-footer__inner{display:flex;gap:18px;justify-content:space-between;padding:18px 0}
CSS;
}

function opus_front_controller(): string
{
    return <<<'PHP'
<?php
declare(strict_types=1);

http_response_code(200);
header('Content-Type: text/plain; charset=UTF-8');
echo "OPUS_SITE_WWW_FRONT_CONTROLLER_OK\n";
PHP;
}

function opus_move_public_and_assets(string $siteRoot): void
{
    $public = $siteRoot . '/public';
    $www = $siteRoot . '/www';
    if (is_dir($public)) {
        opus_copy_recursive($public, $www);
        opus_remove_recursive($public);
    }

    opus_mkdir($www);
    opus_mkdir($www . '/asset/css');
    opus_mkdir($www . '/asset/js');
    opus_mkdir($www . '/asset/themes/starter/css');
    opus_mkdir($www . '/asset/themes/starter/js');
    opus_mkdir($www . '/asset/themes/starter/img');

    foreach (glob($www . '/*.css') ?: [] as $css) {
        rename($css, $www . '/asset/css/' . basename($css));
    }
    foreach (glob($www . '/*.js') ?: [] as $js) {
        rename($js, $www . '/asset/js/' . basename($js));
    }

    $resourcesThemes = $siteRoot . '/resources/themes';
    if (is_dir($resourcesThemes)) {
        opus_copy_recursive($resourcesThemes, $www . '/asset/themes');
    }
}

function opus_collect_controllers(string $siteRoot): array
{
    $controllers = ['home' => ['slug' => 'home', 'path' => '/', 'source' => 'index.php']];

    $routes = opus_read_json($siteRoot . '/config/routes.json');
    foreach (($routes['routes'] ?? []) as $route) {
        if (!is_array($route)) {
            continue;
        }
        $slug = opus_slug((string) ($route['controller'] ?? $route['page'] ?? $route['id'] ?? ''));
        if ($slug === '') {
            continue;
        }
        $controllers[$slug] = [
            'slug' => $slug,
            'path' => (string) ($route['path'] ?? ($slug === 'home' ? '/' : '/' . $slug)),
            'source' => $slug === 'home' ? 'index.php' : $slug . '.php',
        ];
    }

    foreach (glob($siteRoot . '/www/*.php') ?: [] as $php) {
        $name = basename($php);
        $slug = $name === 'index.php' ? 'home' : opus_slug($name);
        $controllers[$slug] = [
            'slug' => $slug,
            'path' => $slug === 'home' ? '/' : '/' . $slug,
            'source' => $name,
        ];
    }

    $application = $siteRoot . '/application';
    if (is_dir($application)) {
        foreach (glob($application . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $slug = basename($dir);
            if ($slug === 'default') {
                continue;
            }
            $controllers[$slug] = $controllers[$slug] ?? [
                'slug' => $slug,
                'path' => $slug === 'home' ? '/' : '/' . $slug,
                'source' => $slug === 'home' ? 'index.php' : $slug . '.php',
            ];
        }
    }

    ksort($controllers);
    if (isset($controllers['home'])) {
        $home = $controllers['home'];
        unset($controllers['home']);
        $controllers = ['home' => $home] + $controllers;
    }

    return array_values($controllers);
}

function opus_prepare_default_layer(string $siteRoot): void
{
    opus_mkdir($siteRoot . '/application/default/acl');
    opus_mkdir($siteRoot . '/application/default/helpers');
    opus_mkdir($siteRoot . '/application/default/css');
    opus_mkdir($siteRoot . '/application/default/javascript');
    opus_mkdir($siteRoot . '/application/default/local/fr');
    opus_mkdir($siteRoot . '/application/default/local/en');
    opus_mkdir($siteRoot . '/application/default/local/es');
    opus_mkdir($siteRoot . '/application/default/models');
    opus_mkdir($siteRoot . '/application/default/templates/components');
    opus_mkdir($siteRoot . '/application/default/views');

    $common = $siteRoot . '/application/common';
    if (is_dir($common)) {
        opus_copy_recursive($common, $siteRoot . '/application/default');
        opus_remove_recursive($common);
    }

    $appConfig = $siteRoot . '/application/config';
    if (is_dir($appConfig)) {
        opus_copy_recursive($appConfig, $siteRoot . '/config');
        opus_remove_recursive($appConfig);
    }

    $resourcesI18n = $siteRoot . '/resources/i18n';
    if (is_dir($resourcesI18n)) {
        foreach (glob($resourcesI18n . '/*.json') ?: [] as $json) {
            $locale = opus_slug(pathinfo($json, PATHINFO_FILENAME));
            opus_mkdir($siteRoot . '/application/default/local/' . $locale);
            copy($json, $siteRoot . '/application/default/local/' . $locale . '/i18n.json');
        }
    }

    if (!is_file($siteRoot . '/application/default/templates/layout.score')) {
        opus_write_text($siteRoot . '/application/default/templates/layout.score', opus_template_layout());
    }
    if (!is_file($siteRoot . '/application/default/templates/components/header.score')) {
        opus_write_text($siteRoot . '/application/default/templates/components/header.score', opus_template_header());
    }
    if (!is_file($siteRoot . '/application/default/templates/components/footer.score')) {
        opus_write_text($siteRoot . '/application/default/templates/components/footer.score', opus_template_footer());
    }
    if (!is_file($siteRoot . '/application/default/templates/components/menu-item.score')) {
        opus_write_text($siteRoot . '/application/default/templates/components/menu-item.score', opus_template_menu_item());
    }
    if (!is_file($siteRoot . '/application/default/templates/components/language-selector.score')) {
        opus_write_text($siteRoot . '/application/default/templates/components/language-selector.score', opus_template_language_selector());
    }
    if (!is_file($siteRoot . '/application/default/templates/components/rubric-card.score')) {
        opus_write_text($siteRoot . '/application/default/templates/components/rubric-card.score', opus_template_rubric_card());
    }
    if (!is_file($siteRoot . '/application/default/css/default.css')) {
        opus_write_text($siteRoot . '/application/default/css/default.css', opus_default_css());
    }
    if (!is_file($siteRoot . '/application/default/javascript/default.js')) {
        opus_write_text($siteRoot . '/application/default/javascript/default.js', "document.documentElement.dataset.opusDefaultLayer='loaded';\n");
    }
}

function opus_prepare_controller(string $siteRoot, array $controller): void
{
    $slug = $controller['slug'];
    foreach (['acl', 'helpers', 'css', 'javascript', 'local/fr', 'local/en', 'local/es', 'models', 'templates', 'views'] as $sub) {
        opus_mkdir($siteRoot . '/application/' . $slug . '/' . $sub);
    }
    if (!is_file($siteRoot . '/application/' . $slug . '/templates/index.score')) {
        opus_write_text($siteRoot . '/application/' . $slug . '/templates/index.score', $slug === 'home' ? opus_template_home() : opus_template_page($slug));
    }
    if (!is_file($siteRoot . '/application/' . $slug . '/css/' . $slug . '.css')) {
        opus_write_text($siteRoot . '/application/' . $slug . '/css/' . $slug . '.css', ".opus-asap-site[data-controller=\"{$slug}\"]{}\n");
    }
    if (!is_file($siteRoot . '/application/' . $slug . '/javascript/' . $slug . '.js')) {
        opus_write_text($siteRoot . '/application/' . $slug . '/javascript/' . $slug . '.js', "document.documentElement.dataset.opusControllerLayer='{$slug}';\n");
    }
    foreach (['fr', 'en', 'es'] as $locale) {
        $path = $siteRoot . '/application/' . $slug . '/local/' . $locale . '/i18n.json';
        if (!is_file($path)) {
            opus_json_write($path, [
                'page.kicker' => strtoupper($slug),
                'page.title' => opus_title($slug),
                'page.subtitle' => 'Page OPUS migrée sous contrat ASAP éternel.',
                'page.section_title' => opus_title($slug),
                'page.section_intro' => 'Ressources dans application/' . $slug . ' avec héritage default + thème + controller.',
            ]);
        }
    }

    $source = $siteRoot . '/www/' . $controller['source'];
    if (is_file($source)) {
        copy($source, $siteRoot . '/application/' . $slug . '/views/legacy-public-entry.php');
    }
}

function opus_migrate_pages_folder(string $siteRoot): void
{
    $pages = $siteRoot . '/application/pages';
    if (!is_dir($pages)) {
        return;
    }
    foreach (glob($pages . '/*.score') ?: [] as $score) {
        $slug = opus_slug(pathinfo($score, PATHINFO_FILENAME));
        opus_mkdir($siteRoot . '/application/' . $slug . '/templates');
        copy($score, $siteRoot . '/application/' . $slug . '/templates/index.score');
    }
    opus_remove_recursive($pages);
}

function opus_write_site_config(string $siteRoot, string $siteId, array $controllers): void
{
    opus_mkdir($siteRoot . '/config');
    $old = opus_read_json($siteRoot . '/config/site.json');
    $siteName = (string) ($old['site_name'] ?? $old['name'] ?? ('OPUS ' . $siteId));
    opus_json_write($siteRoot . '/config/site.json', [
        'site_id' => $siteId,
        'site_name' => $siteName,
        'contract' => 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
        'starter_contract' => 'OPUS_ASAP_INHERITANCE_STARTER_V1',
        'default_locale' => (string) ($old['default_locale'] ?? 'fr'),
        'locales' => array_values(array_unique(array_merge(['fr', 'en', 'es'], array_filter((array) ($old['locales'] ?? []), 'is_scalar')))),
        'theme' => (string) ($old['theme'] ?? 'starter'),
        'application_root' => 'application',
        'default_root' => 'application/default',
        'controller_root_pattern' => 'application/<controller>',
        'public_root' => 'www',
        'asset_root' => 'www/asset',
        'theme_root_pattern' => 'www/asset/themes/<theme>',
        'css_inheritance' => ['application/default/css', 'www/asset/themes/<theme>/css', 'application/<controller>/css'],
        'js_inheritance' => ['application/default/javascript', 'www/asset/themes/<theme>/js', 'application/<controller>/javascript'],
        'controllers' => array_column($controllers, 'slug'),
        'home_route' => 'home.index',
    ]);
}

function opus_write_registries(string $siteRoot, array $controllers): void
{
    $routes = [];
    $order = 10;
    foreach ($controllers as $controller) {
        $slug = $controller['slug'];
        $routes[] = [
            'id' => $slug . '.index',
            'path' => $slug === 'home' ? '/' : '/' . $slug,
            'page' => $slug,
            'controller' => $slug,
            'action' => 'index',
            'template' => 'application/' . $slug . '/templates/index.score',
            'label' => 'menu.' . $slug,
            'acl' => 'public',
            'fsm_state' => strtoupper(str_replace('-', '_', $slug)),
            'show_in_menu' => true,
            'show_on_home' => $slug !== 'home',
            'order' => $order,
        ];
        $order += 10;
    }
    opus_json_write($siteRoot . '/config/routes.json', ['contract' => 'OPUS_ROUTE_REGISTRY_V1', 'routes' => $routes]);
    opus_json_write($siteRoot . '/config/menu.json', ['contract' => 'OPUS_MENU_ROUTE_PROJECTION_V1', 'source' => 'config/routes.json', 'items' => array_map(static fn(array $r): array => ['route' => $r['id'], 'controller' => $r['controller'], 'label' => $r['label'], 'order' => $r['order']], $routes)]);
    opus_json_write($siteRoot . '/config/rubrics.json', ['contract' => 'OPUS_HOME_DEMO_CARD_ROUTE_PROJECTION_V1', 'source' => 'config/routes.json', 'rubrics' => array_values(array_map(static fn(array $r): array => ['route' => $r['id'], 'controller' => $r['controller'], 'order' => $r['order']], array_filter($routes, static fn(array $r): bool => $r['controller'] !== 'home')))]);
    opus_json_write($siteRoot . '/config/fsm.json', ['contract' => 'OPUS_FSM_REGISTRY_V1', 'initial_state' => 'HOME', 'states' => array_map(static fn(array $r): array => ['id' => $r['fsm_state'], 'controller' => $r['controller'], 'route' => $r['id'], 'role' => 'site-page'], $routes), 'transitions' => []]);
}

function opus_migrate_site(string $siteRoot): void
{
    $siteId = basename($siteRoot);
    opus_mkdir($siteRoot . '/config');
    opus_mkdir($siteRoot . '/application');
    opus_move_public_and_assets($siteRoot);
    opus_prepare_default_layer($siteRoot);
    opus_migrate_pages_folder($siteRoot);
    $controllers = opus_collect_controllers($siteRoot);
    foreach ($controllers as $controller) {
        opus_prepare_controller($siteRoot, $controller);
    }
    opus_write_site_config($siteRoot, $siteId, $controllers);
    opus_write_registries($siteRoot, $controllers);
    if (!is_file($siteRoot . '/www/index.php')) {
        opus_write_text($siteRoot . '/www/index.php', opus_front_controller());
    }
    if (!is_file($siteRoot . '/www/asset/themes/starter/css/theme.css')) {
        opus_write_text($siteRoot . '/www/asset/themes/starter/css/theme.css', "body.opus-asap-site{--opus-blue:#24466d}\n");
    }
    if (!is_file($siteRoot . '/www/asset/themes/starter/js/theme.js')) {
        opus_write_text($siteRoot . '/www/asset/themes/starter/js/theme.js', "document.documentElement.dataset.opusThemeLayer='starter';\n");
    }
    if (is_dir($siteRoot . '/resources')) {
        opus_remove_recursive($siteRoot . '/resources');
    }
    echo "OPUS_SITE_REORGANIZED: {$siteId}\n";
}

opus_ensure_scaffold_roots($root);
opus_ensure_bin_contract($root);
opus_fix_php_file($root . '/tools/smoke_opus_site_contract_eternal.php');

$sitesRoot = $root . '/sites';
if (!is_dir($sitesRoot)) {
    fwrite(STDERR, "OPUS_SITES_DIRECTORY_MISSING\n");
    exit(1);
}

$sites = glob($sitesRoot . '/*', GLOB_ONLYDIR) ?: [];
if ($sites === []) {
    fwrite(STDERR, "OPUS_NO_SITES_FOUND\n");
    exit(1);
}

foreach ($sites as $siteRoot) {
    opus_migrate_site(str_replace('\\', '/', $siteRoot));
}

echo "OPUS_ALL_SITES_REORGANIZED_UNDER_ETERNAL_ASAP_CONTRACT\n";
