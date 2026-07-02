<?php
declare(strict_types=1);

$root = getcwd();
$publicDir = $root . '/sites/opus-p7-ops/public';
$siteDir = $root . '/sites/opus-p7-ops';

function p7v_read(string $file): string
{
    $source = file_get_contents($file);
    if ($source === false) {
        fwrite(STDERR, 'P7_VISIBLE_PROFILER_READ_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }
    return $source;
}

function p7v_write(string $file, string $source): void
{
    if (file_put_contents($file, $source) === false) {
        fwrite(STDERR, 'P7_VISIBLE_PROFILER_WRITE_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }
}

$languageFile = $publicDir . '/language.php';
$routerFile = $publicDir . '/router.php';
$cssFile = $publicDir . '/ops-ui.css';
$readmeFile = $siteDir . '/README.md';

foreach ([$languageFile, $routerFile, $cssFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, 'P7_VISIBLE_PROFILER_FILE_MISSING: ' . $file . PHP_EOL);
        exit(1);
    }
}

$language = p7v_read($languageFile);

$runtime = <<<'PHP'

/** P7_OPS_PROFILER_VISIBLE_MODE_CORE */
if (!function_exists('p7ops_profiler_visible_escape')) {
    function p7ops_profiler_visible_escape(string $value): string
    {
        if (function_exists('p7ops_h')) {
            return p7ops_h($value);
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('p7ops_profiler_visible_enabled')) {
    function p7ops_profiler_visible_enabled(): bool
    {
        if (PHP_SAPI === 'cli') {
            return (string) ($_GET['profiler'] ?? '') === '1';
        }

        if (function_exists('p7ops_clean_profiler_enabled') && p7ops_clean_profiler_enabled()) {
            return true;
        }

        if (function_exists('p7ops_sf_profiler_enabled') && p7ops_sf_profiler_enabled()) {
            return true;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('OPUSLSTSAROPS');
            @session_start();
        }

        return (bool) (
            ($_SESSION['p7ops_clean_profiler_enabled'] ?? false)
            || ($_SESSION['p7ops_sf_profiler_enabled'] ?? false)
            || ($_SESSION['p7ops_profiler_enabled'] ?? false)
        );
    }
}

if (!function_exists('p7ops_profiler_visible_static_path')) {
    function p7ops_profiler_visible_static_path(string $path): bool
    {
        return (bool) preg_match('~\.(?:ico|png|jpe?g|gif|svg|webp|css|js|map|woff2?|ttf|eot)$~i', $path);
    }
}

if (!function_exists('p7ops_profiler_visible_badge_html')) {
    function p7ops_profiler_visible_badge_html(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/opus-lstsar-manager');
        $path = rawurldecode((string) (parse_url($uri, PHP_URL_PATH) ?: '/opus-lstsar-manager'));
        $start = (float) ($GLOBALS['p7ops_visible_profiler_started_at'] ?? microtime(true));
        $duration = number_format((microtime(true) - $start) * 1000, 2, '.', '');

        return '<div class="opus-profiler-visible-ribbon" data-contract="P7_OPS_PROFILER_VISIBLE_MODE_CORE">'
            . '<strong>PROFILER ACTIVE</strong>'
            . '<span>' . p7ops_profiler_visible_escape($path) . '</span>'
            . '<span>' . p7ops_profiler_visible_escape($duration) . ' ms</span>'
            . '<a href="/opus-lstsar-manager/profiler">Open profiler</a>'
            . '<a href="/opus-lstsar-manager/profiler/exit?next=' . p7ops_profiler_visible_escape(rawurlencode($uri)) . '">Exit</a>'
            . '</div>';
    }
}

if (!function_exists('p7ops_profiler_visible_apply_html')) {
    function p7ops_profiler_visible_apply_html(string $html): string
    {
        if ($html === '' || str_contains($html, 'P7_OPS_PROFILER_VISIBLE_MODE_CORE')) {
            return $html;
        }

        $ribbon = p7ops_profiler_visible_badge_html();

        if (stripos($html, '<body') !== false) {
            $html = preg_replace('~<body\b([^>]*)>~i', '<body$1 class="opus-profiler-visible-active">', $html, 1) ?: $html;
        }

        if (stripos($html, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $ribbon . '</body>', $html, 1) ?: ($html . $ribbon);
        }

        return $html . $ribbon;
    }
}

if (!function_exists('p7ops_profiler_visible_boot_once')) {
    function p7ops_profiler_visible_boot_once(): void
    {
        static $booted = false;
        if ($booted || PHP_SAPI === 'cli') {
            return;
        }

        $booted = true;
        $path = rawurldecode((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ''));

        if (p7ops_profiler_visible_static_path($path) || !p7ops_profiler_visible_enabled()) {
            return;
        }

        $GLOBALS['p7ops_visible_profiler_started_at'] = microtime(true);
        ob_start(static function (string $html): string {
            return p7ops_profiler_visible_apply_html($html);
        });
    }
}
PHP;

if (!str_contains($language, 'P7_OPS_PROFILER_VISIBLE_MODE_CORE')) {
    $language .= PHP_EOL . $runtime . PHP_EOL;
}
p7v_write($languageFile, $language);

$router = p7v_read($routerFile);
if (!str_contains($router, 'p7ops_profiler_visible_boot_once();')) {
    $inserted = false;
    foreach (['p7ops_unified_navigation_boot_once();', 'p7ops_clean_profiler_boot_once();', 'p7ops_sf_profiler_boot_once();'] as $needle) {
        if (str_contains($router, $needle)) {
            $router = str_replace($needle, $needle . PHP_EOL . 'p7ops_profiler_visible_boot_once();', $router);
            $inserted = true;
            break;
        }
    }

    if (!$inserted) {
        $require = "require_once __DIR__ . '/language.php';";
        $router = str_replace($require, $require . PHP_EOL . 'p7ops_profiler_visible_boot_once();', $router);
    }
}

if (!str_contains($router, 'P7_OPS_PROFILER_VISIBLE_MODE_CORE')) {
    $router = str_replace('<?php', "<?php\n/** P7_OPS_PROFILER_VISIBLE_MODE_CORE */", $router);
}
p7v_write($routerFile, $router);

$css = p7v_read($cssFile);
if (!str_contains($css, 'P7_OPS_PROFILER_VISIBLE_MODE_CORE')) {
    $css .= PHP_EOL;
    $css .= '/* P7_OPS_PROFILER_VISIBLE_MODE_CORE */' . PHP_EOL;
    $css .= 'body.opus-profiler-visible-active{outline:6px solid #f59e0b!important;outline-offset:-6px;background-image:linear-gradient(135deg,rgba(245,158,11,.08) 0,rgba(245,158,11,.08) 25%,transparent 25%,transparent 50%,rgba(245,158,11,.08) 50%,rgba(245,158,11,.08) 75%,transparent 75%,transparent)!important;background-size:18px 18px!important}' . PHP_EOL;
    $css .= '.opus-profiler-visible-ribbon{position:fixed;left:1rem;right:1rem;bottom:1rem;z-index:10000;display:flex;flex-wrap:wrap;align-items:center;gap:.6rem;border:2px solid #f59e0b;border-radius:999px;background:#111827;color:#fef3c7;box-shadow:0 18px 45px rgba(0,0,0,.45);padding:.65rem .85rem;font:13px/1.35 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}' . PHP_EOL;
    $css .= '.opus-profiler-visible-ribbon strong{letter-spacing:.09em;color:#fbbf24}.opus-profiler-visible-ribbon span{border-left:1px solid rgba(251,191,36,.35);padding-left:.6rem;overflow-wrap:anywhere}.opus-profiler-visible-ribbon a{margin-left:auto;border:1px solid rgba(251,191,36,.55);border-radius:999px;padding:.35rem .6rem;background:#451a03;color:#fffbeb;text-decoration:none;font-weight:900}.opus-profiler-visible-ribbon a+a{margin-left:0;background:#7f1d1d;border-color:#f87171}' . PHP_EOL;
    $css .= '.opus-unified-nav:has(+ .opus-profiler-visible-ribbon),.opus-unified-nav{transition:box-shadow .2s ease}.opus-profiler-visible-active .opus-unified-nav{box-shadow:0 0 0 3px rgba(245,158,11,.35),0 18px 50px rgba(0,0,0,.28)!important}' . PHP_EOL;
    $css .= '@media(max-width:800px){.opus-profiler-visible-ribbon{left:.5rem;right:.5rem;bottom:.5rem;border-radius:1rem}.opus-profiler-visible-ribbon a{margin-left:0}}' . PHP_EOL;
}
p7v_write($cssFile, $css);

$readme = is_file($readmeFile) ? p7v_read($readmeFile) : '# OPUS P7 OPS' . PHP_EOL;
if (!str_contains($readme, 'P7_OPS_PROFILER_VISIBLE_MODE_CORE')) {
    $readme .= PHP_EOL;
    $readme .= '## P7_OPS_PROFILER_VISIBLE_MODE_CORE' . PHP_EOL . PHP_EOL;
    $readme .= '- Makes profiler mode visually obvious: page outline, amber debug background and persistent bottom ribbon.' . PHP_EOL;
    $readme .= '- The ribbon shows current path, request duration, Open profiler and Exit actions.' . PHP_EOL;
    $readme .= '- Static assets are excluded from visible profiler decoration.' . PHP_EOL;
}
p7v_write($readmeFile, $readme);

echo 'P7_OPS_PROFILER_VISIBLE_MODE_CORE_UPDATED' . PHP_EOL;
