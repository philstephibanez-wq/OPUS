<?php
declare(strict_types=1);

$root = getcwd();
$siteDir = $root . '/sites/opus-p7-ops';
$publicDir = $siteDir . '/public';
$logDir = $root . '/var/logs/opus_lstsar-manager';

function nav_read(string $file): string {
    $s = file_get_contents($file);
    if ($s === false) { fwrite(STDERR, 'NAV_READ_FAILED: ' . $file . PHP_EOL); exit(1); }
    return $s;
}
function nav_write(string $file, string $s): void {
    if (file_put_contents($file, $s) === false) { fwrite(STDERR, 'NAV_WRITE_FAILED: ' . $file . PHP_EOL); exit(1); }
}

if (!is_dir($publicDir)) { fwrite(STDERR, 'NAV_PUBLIC_DIR_MISSING' . PHP_EOL); exit(1); }
if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) { fwrite(STDERR, 'NAV_LOG_DIR_CREATE_FAILED' . PHP_EOL); exit(1); }
file_put_contents($root . '/var/logs/.gitignore', "*.log\n**/*.log\n!**/.gitkeep\n");
file_put_contents($logDir . '/.gitkeep', '');

$languageFile = $publicDir . '/language.php';
$language = nav_read($languageFile);

$runtime = <<<'PHP'

/** P7_OPS_UNIFIED_ERGONOMIC_NAVIGATION_CORE */
if (!function_exists('p7ops_nav_h')) {
    function p7ops_nav_h(string $value): string
    {
        return function_exists('p7ops_h') ? p7ops_h($value) : htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('p7ops_nav_path')) {
    function p7ops_nav_path(): string
    {
        return rawurldecode((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/opus-lstsar-manager'), PHP_URL_PATH) ?: '/opus-lstsar-manager'));
    }
}

if (!function_exists('p7ops_nav_static')) {
    function p7ops_nav_static(string $path): bool
    {
        return (bool) preg_match('~\.(?:ico|png|jpe?g|gif|svg|webp|css|js|map|woff2?|ttf|eot)$~i', $path);
    }
}

if (!function_exists('p7ops_nav_profiler_on')) {
    function p7ops_nav_profiler_on(): bool
    {
        return (function_exists('p7ops_clean_profiler_enabled') && p7ops_clean_profiler_enabled())
            || (function_exists('p7ops_sf_profiler_enabled') && p7ops_sf_profiler_enabled())
            || ((string) ($_GET['profiler'] ?? '') === '1');
    }
}

if (!function_exists('p7ops_nav_query')) {
    function p7ops_nav_query(array $extra = []): string
    {
        $lang = (string) ($_GET['lang'] ?? (function_exists('p7ops_language') ? p7ops_language() : 'fr'));
        $site = (string) ($_GET['site'] ?? 'site-alpha');
        $query = array_merge(['site' => $site, 'lang' => $lang], $extra);
        if (p7ops_nav_profiler_on()) { $query['profiler'] = '1'; }
        return '?' . http_build_query($query);
    }
}

if (!function_exists('p7ops_nav_groups')) {
    function p7ops_nav_groups(): array
    {
        return [
            'Pilotage' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => '/opus-lstsar-manager'],
                ['key' => 'operations', 'label' => 'Operations', 'route' => '/opus-lstsar-manager/operations'],
                ['key' => 'command', 'label' => 'Command Center', 'route' => '/opus-lstsar-manager/command-center'],
            ],
            'Chaîne' => [
                ['key' => 'chain', 'label' => 'Chaîne complète', 'route' => '/opus-lstsar-manager/chain'],
                ['key' => 'fsm', 'label' => 'FSM', 'route' => '/opus-lstsar-manager/fsm'],
                ['key' => 'cl', 'label' => 'CL', 'route' => '/opus-lstsar-manager/cl'],
                ['key' => 'models', 'label' => 'Models', 'route' => '/opus-lstsar-manager/models'],
                ['key' => 'odbc', 'label' => 'ODBC Manager', 'route' => '/opus-lstsar-manager/odbc-manager'],
            ],
            'Observabilité' => [
                ['key' => 'profiler', 'label' => 'Profiler', 'route' => '/opus-lstsar-manager/profiler'],
                ['key' => 'diagnostics', 'label' => 'Diagnostics', 'route' => '/opus-lstsar-manager/diagnostics'],
                ['key' => 'health', 'label' => 'Health', 'route' => '/opus-lstsar-manager/health'],
            ],
        ];
    }
}

if (!function_exists('p7ops_nav_active')) {
    function p7ops_nav_active(): string
    {
        $path = p7ops_nav_path();
        $map = [
            '/opus-lstsar-manager' => 'dashboard', '/' => 'dashboard',
            '/opus-lstsar-manager/operations' => 'operations', '/opus-lstsar-manager/action' => 'operations',
            '/opus-lstsar-manager/command' => 'command', '/opus-lstsar-manager/command-center' => 'command',
            '/opus-lstsar-manager/navigation' => 'dashboard', '/opus-lstsar-manager/navigation-polish' => 'dashboard',
            '/opus-lstsar-manager/chain' => 'chain', '/opus-lstsar-manager/dependency-chain' => 'chain',
            '/opus-lstsar-manager/fsm' => 'fsm', '/opus-lstsar-manager/cl' => 'cl',
            '/opus-lstsar-manager/models' => 'models', '/opus-lstsar-manager/odbc-manager' => 'odbc',
            '/opus-lstsar-manager/profiler' => 'profiler',
            '/opus-lstsar-manager/diagnostics' => 'diagnostics', '/opus-lstsar-manager/runtime-diagnostics' => 'diagnostics',
            '/opus-lstsar-manager/health' => 'health', '/opus-lstsar-manager/health-hub' => 'health',
        ];
        return $map[$path] ?? 'dashboard';
    }
}

if (!function_exists('p7ops_nav_context')) {
    function p7ops_nav_context(): string
    {
        $active = p7ops_nav_active();
        foreach (p7ops_nav_groups() as $group => $items) {
            foreach ($items as $item) {
                if ((string) $item['key'] === $active) { return $group . ' / ' . (string) $item['label']; }
            }
        }
        return 'Pilotage / Dashboard';
    }
}

if (!function_exists('p7ops_nav_html')) {
    function p7ops_nav_html(): string
    {
        $active = p7ops_nav_active();
        $path = p7ops_nav_path();
        $lang = (string) ($_GET['lang'] ?? (function_exists('p7ops_language') ? p7ops_language() : 'fr'));
        $site = (string) ($_GET['site'] ?? 'site-alpha');
        $prof = p7ops_nav_profiler_on();
        $langs = ['fr' => 'Français', 'en' => 'English', 'es' => 'Español', 'de' => 'Deutsch', 'it' => 'Italiano', 'pt' => 'Português', 'uk' => 'Українська'];
        $html = '<header class="opus-unified-nav" data-contract="P7_OPS_UNIFIED_ERGONOMIC_NAVIGATION_CORE"><div class="oun-main">';
        $html .= '<a class="oun-brand" href="/opus-lstsar-manager' . p7ops_nav_h(p7ops_nav_query()) . '"><strong>OPUS OPS</strong><span>LSTSAR Manager</span></a>';
        $html .= '<nav class="oun-groups" aria-label="Navigation principale">';
        foreach (p7ops_nav_groups() as $group => $items) {
            $html .= '<section class="oun-group"><h2>' . p7ops_nav_h((string) $group) . '</h2><div>';
            foreach ($items as $item) {
                $key = (string) $item['key'];
                $class = $key === $active ? ' class="is-active"' : '';
                $html .= '<a' . $class . ' href="' . p7ops_nav_h((string) $item['route'] . p7ops_nav_query()) . '">' . p7ops_nav_h((string) $item['label']) . '</a>';
            }
            $html .= '</div></section>';
        }
        $html .= '</nav><form class="oun-lang" method="get" action="' . p7ops_nav_h($path) . '"><input type="hidden" name="site" value="' . p7ops_nav_h($site) . '">';
        if ($prof) { $html .= '<input type="hidden" name="profiler" value="1">'; }
        $html .= '<label>Langue <select name="lang" onchange="this.form.submit()">';
        foreach ($langs as $code => $label) {
            $html .= '<option value="' . p7ops_nav_h($code) . '"' . ($code === $lang ? ' selected' : '') . '>' . p7ops_nav_h($label . ' — ' . strtoupper($code)) . '</option>';
        }
        $html .= '</select></label></form></div><div class="oun-context">';
        $html .= '<span><strong>Parcours :</strong> ' . p7ops_nav_h(p7ops_nav_context()) . '</span><span><strong>Site :</strong> ' . p7ops_nav_h($site) . '</span>';
        $html .= '<span><strong>Env :</strong> ' . p7ops_nav_h(function_exists('p7ops_environment') ? p7ops_environment() : 'dev') . '</span>';
        if ($prof) {
            $html .= '<a class="oun-profiler is-on" href="/opus-lstsar-manager/profiler">Profiler ON</a><a class="oun-exit" href="/opus-lstsar-manager/profiler/exit?next=' . p7ops_nav_h(rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? '/opus-lstsar-manager'))) . '">Sortir profiler</a>';
        } else {
            $html .= '<a class="oun-profiler" href="' . p7ops_nav_h($path . p7ops_nav_query(['profiler' => '1'])) . '">Activer profiler</a>';
        }
        return $html . '</div></header>';
    }
}

if (!function_exists('p7ops_nav_cleanup_legacy')) {
    function p7ops_nav_cleanup_legacy(string $html): string
    {
        foreach ([
            '~<section\b[^>]*class="[^"]*ops-panel[^"]*"[^>]*>\s*<div\b[^>]*class="[^"]*ops-topline[^"]*"[^>]*>\s*<span\b[^>]*class="[^"]*ops-badge[^"]*"[^>]*>P7_OPS_CHAIN_AUTH_ENV_CORE.*?</section>~is',
            '~<section\b[^>]*class="[^"]*ops-panel[^"]*"[^>]*>.*?OPUS P7 OPS SITE.*?</section>~is',
        ] as $pattern) { $html = preg_replace($pattern, '', $html, 1) ?? $html; }
        return $html;
    }
}

if (!function_exists('p7ops_unified_navigation_boot_once')) {
    function p7ops_unified_navigation_boot_once(): void
    {
        static $booted = false;
        if ($booted || PHP_SAPI === 'cli') { return; }
        $booted = true;
        if (p7ops_nav_static(p7ops_nav_path())) { return; }
        ob_start(static function (string $html): string {
            if ($html === '' || str_contains($html, 'P7_OPS_UNIFIED_ERGONOMIC_NAVIGATION_CORE')) { return $html; }
            $html = p7ops_nav_cleanup_legacy($html);
            $nav = p7ops_nav_html();
            if (stripos($html, '<body') !== false) { return preg_replace('~(<body\b[^>]*>)~i', '$1' . $nav, $html, 1) ?: ($nav . $html); }
            return $nav . $html;
        });
    }
}
PHP;

if (!str_contains($language, 'P7_OPS_UNIFIED_ERGONOMIC_NAVIGATION_CORE')) {
    $language .= PHP_EOL . $runtime . PHP_EOL;
}
nav_write($languageFile, $language);

$routerFile = $publicDir . '/router.php';
$router = nav_read($routerFile);
if (!str_contains($router, 'p7ops_unified_navigation_boot_once();')) {
    if (str_contains($router, 'p7ops_clean_profiler_boot_once();')) {
        $router = str_replace('p7ops_clean_profiler_boot_once();', 'p7ops_clean_profiler_boot_once();' . PHP_EOL . 'p7ops_unified_navigation_boot_once();', $router);
    } elseif (str_contains($router, "require_once __DIR__ . '/language.php';")) {
        $router = str_replace("require_once __DIR__ . '/language.php';", "require_once __DIR__ . '/language.php';" . PHP_EOL . 'p7ops_unified_navigation_boot_once();', $router);
    }
}
if (!str_contains($router, 'P7_OPS_UNIFIED_ERGONOMIC_NAVIGATION_CORE')) {
    $router = str_replace('<?php', "<?php\n/** P7_OPS_UNIFIED_ERGONOMIC_NAVIGATION_CORE */", $router);
}
nav_write($routerFile, $router);

$cssFile = $publicDir . '/ops-ui.css';
$css = is_file($cssFile) ? nav_read($cssFile) : '';
if (!str_contains($css, 'P7_OPS_UNIFIED_ERGONOMIC_NAVIGATION_CORE')) {
    $css .= PHP_EOL . '/* P7_OPS_UNIFIED_ERGONOMIC_NAVIGATION_CORE */' . PHP_EOL;
    $css .= 'html,body{max-width:100%;overflow-x:hidden}.ops-language-selector,[class*="language-selector"],[class*="LanguageSelector"]{display:none!important}.opus-unified-nav{box-sizing:border-box;width:min(1440px,calc(100vw - 1.5rem));margin:.75rem auto 1.2rem;border:1px solid rgba(96,165,250,.38);border-radius:20px;background:linear-gradient(180deg,rgba(15,23,42,.98),rgba(2,6,23,.96));box-shadow:0 18px 50px rgba(0,0,0,.28);color:#f8fafc;font:14px/1.4 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;position:sticky;top:.5rem;z-index:4200}.oun-main{display:grid;grid-template-columns:minmax(12rem,16rem) minmax(0,1fr) minmax(14rem,18rem);gap:1rem;align-items:stretch;padding:1rem}.oun-brand{display:grid;align-content:center;gap:.1rem;text-decoration:none;color:#f8fafc;border:1px solid rgba(34,211,238,.25);border-radius:16px;padding:.8rem;background:#020617}.oun-brand strong{font-size:1.25rem;color:#67e8f9;letter-spacing:.04em}.oun-brand span{color:#cbd5e1;font-weight:700}.oun-groups{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;min-width:0}.oun-group{min-width:0;border:1px solid rgba(96,165,250,.22);border-radius:16px;padding:.65rem;background:rgba(2,6,23,.45)}.oun-group h2{margin:0 0 .45rem;color:#93c5fd;font-size:.75rem;text-transform:uppercase;letter-spacing:.08em}.oun-group div{display:flex;flex-wrap:wrap;gap:.4rem}.oun-group a,.oun-profiler,.oun-exit{display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(96,165,250,.35);border-radius:999px;padding:.45rem .65rem;background:#020617;color:#f8fafc;text-decoration:none;font-weight:800;white-space:nowrap}.oun-group a.is-active{border-color:#22d3ee;background:#155e75;color:#ecfeff}.oun-profiler.is-on{border-color:#22c55e;background:#14532d}.oun-exit{border-color:#f87171;background:#7f1d1d}.oun-lang{display:grid;align-content:center;gap:.35rem;border:1px solid rgba(96,165,250,.22);border-radius:16px;padding:.75rem;background:rgba(2,6,23,.45)}.oun-lang label{display:grid;gap:.35rem;color:#cbd5e1;font-weight:800}.oun-lang select{width:100%;border:1px solid rgba(96,165,250,.35);border-radius:999px;background:#e2e8f0;color:#0f172a;padding:.55rem .7rem;font-weight:900}.oun-context{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;border-top:1px solid rgba(96,165,250,.22);padding:.75rem 1rem;color:#cbd5e1}.oun-context span,.oun-context a{border:1px solid rgba(96,165,250,.22);border-radius:999px;padding:.4rem .65rem;background:rgba(2,6,23,.55)}.oun-context strong{color:#67e8f9}.oun-context a{text-decoration:none;color:#f8fafc;font-weight:800}.ops-shell{width:min(1280px,calc(100vw - 1.5rem))!important;max-width:calc(100vw - 1.5rem)!important;margin-inline:auto!important}.ops-table-wrap{overflow-x:auto}.ops-table{table-layout:auto!important}.ops-table code,.ops-table td code,.ops-card code{white-space:nowrap!important;overflow-wrap:normal!important;word-break:normal!important}.ops-kv-grid,.ops-summary-grid{grid-template-columns:repeat(auto-fit,minmax(14rem,1fr))!important}@media(max-width:1200px){.oun-main{grid-template-columns:1fr}.oun-groups{grid-template-columns:1fr}.opus-unified-nav{position:relative;top:auto}.oun-lang{order:3}}@media(max-width:720px){.opus-unified-nav{width:calc(100vw - .75rem);margin:.35rem auto}.oun-main{padding:.65rem}.oun-context{padding:.65rem}.oun-group div{display:grid;grid-template-columns:1fr 1fr}.oun-group a{white-space:normal}}' . PHP_EOL;
}
nav_write($cssFile, $css);

$readmeFile = $siteDir . '/README.md';
$readme = is_file($readmeFile) ? nav_read($readmeFile) : '# OPUS P7 OPS' . PHP_EOL;
if (!str_contains($readme, 'P7_OPS_UNIFIED_ERGONOMIC_NAVIGATION_CORE')) {
    $readme .= PHP_EOL . '## P7_OPS_UNIFIED_ERGONOMIC_NAVIGATION_CORE' . PHP_EOL . PHP_EOL;
    $readme .= '- Adds one stable professional navigation for every OPS page.' . PHP_EOL;
    $readme .= '- Groups routes into Pilotage, Chaîne and Observabilité instead of random back/other links.' . PHP_EOL;
    $readme .= '- Preserves site, lang and session profiler context across navigation.' . PHP_EOL;
    $readme .= '- Removes legacy floating language/navigation headers from rendered pages.' . PHP_EOL;
}
nav_write($readmeFile, $readme);

echo 'P7_OPS_UNIFIED_ERGONOMIC_NAVIGATION_CORE_UPDATED' . PHP_EOL;
