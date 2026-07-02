<?php
declare(strict_types=1);

$root = getcwd();
$publicDir = $root . '/sites/opus-p7-ops/public';
$siteDir = $root . '/sites/opus-p7-ops';

function p7open_read(string $file): string
{
    $source = file_get_contents($file);
    if ($source === false) {
        fwrite(STDERR, 'P7_PROFILER_OPEN_READ_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }
    return $source;
}

function p7open_write(string $file, string $source): void
{
    if (file_put_contents($file, $source) === false) {
        fwrite(STDERR, 'P7_PROFILER_OPEN_WRITE_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }
}

$languageFile = $publicDir . '/language.php';
$routerFile = $publicDir . '/router.php';
$profilerFile = $publicDir . '/profiler.php';
$readmeFile = $siteDir . '/README.md';

foreach ([$languageFile, $routerFile, $profilerFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, 'P7_PROFILER_OPEN_FILE_MISSING: ' . $file . PHP_EOL);
        exit(1);
    }
}

$language = p7open_read($languageFile);

$helpers = <<<'PHP'

/** P7_OPS_PROFILER_OPEN_CONTEXT_CORE */
if (!function_exists('p7ops_profiler_context_is_profiler_page')) {
    function p7ops_profiler_context_is_profiler_page(?string $path = null): bool
    {
        $path = $path ?? rawurldecode((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ''));
        return in_array($path, ['/opus-lstsar-manager/profiler', '/_profiler'], true);
    }
}

if (!function_exists('p7ops_profiler_context_is_profiler_exit')) {
    function p7ops_profiler_context_is_profiler_exit(?string $path = null): bool
    {
        $path = $path ?? rawurldecode((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ''));
        return in_array($path, ['/opus-lstsar-manager/profiler/exit', '/_profiler/exit'], true);
    }
}

if (!function_exists('p7ops_profiler_context_default_app_uri')) {
    function p7ops_profiler_context_default_app_uri(): string
    {
        $site = (string) ($_GET['site'] ?? 'site-alpha');
        $lang = (string) ($_GET['lang'] ?? (function_exists('p7ops_language') ? p7ops_language() : 'fr'));
        return '/opus-lstsar-manager/operations?' . http_build_query(['site' => $site, 'lang' => $lang, 'profiler' => '1']);
    }
}

if (!function_exists('p7ops_profiler_context_store_app_uri')) {
    function p7ops_profiler_context_store_app_uri(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = rawurldecode((string) (parse_url($uri, PHP_URL_PATH) ?: ''));

        $isStatic = function_exists('p7ops_profiler_visible_static_path')
            ? p7ops_profiler_visible_static_path($path)
            : (bool) preg_match('~\.(?:ico|png|jpe?g|gif|svg|webp|css|js|map|woff2?|ttf|eot)$~i', $path);

        if ($uri === '' || $isStatic || p7ops_profiler_context_is_profiler_page($path) || p7ops_profiler_context_is_profiler_exit($path)) {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('OPUSLSTSAROPS');
            @session_start();
        }

        $_SESSION['p7ops_profiler_last_app_uri'] = $uri;
    }
}

if (!function_exists('p7ops_profiler_context_last_app_uri')) {
    function p7ops_profiler_context_last_app_uri(): string
    {
        if (PHP_SAPI !== 'cli') {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_name('OPUSLSTSAROPS');
                @session_start();
            }

            $uri = (string) ($_SESSION['p7ops_profiler_last_app_uri'] ?? '');
            if ($uri !== '') {
                return $uri;
            }
        }

        return p7ops_profiler_context_default_app_uri();
    }
}
PHP;

if (!str_contains($language, 'P7_OPS_PROFILER_OPEN_CONTEXT_CORE')) {
    $language .= PHP_EOL . $helpers . PHP_EOL;
}

$newBadge = <<<'PHP'
if (!function_exists('p7ops_profiler_visible_badge_html')) {
    function p7ops_profiler_visible_badge_html(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/opus-lstsar-manager');
        $path = rawurldecode((string) (parse_url($uri, PHP_URL_PATH) ?: '/opus-lstsar-manager'));
        $start = (float) ($GLOBALS['p7ops_visible_profiler_started_at'] ?? microtime(true));
        $duration = number_format((microtime(true) - $start) * 1000, 2, '.', '');

        $isProfilerPage = function_exists('p7ops_profiler_context_is_profiler_page') && p7ops_profiler_context_is_profiler_page($path);
        $primaryHref = $isProfilerPage && function_exists('p7ops_profiler_context_last_app_uri')
            ? p7ops_profiler_context_last_app_uri()
            : '/opus-lstsar-manager/profiler';
        $primaryLabel = $isProfilerPage ? 'Back to app' : 'Open profiler';

        return '<div class="opus-profiler-visible-ribbon" data-contract="P7_OPS_PROFILER_VISIBLE_MODE_CORE P7_OPS_PROFILER_OPEN_CONTEXT_CORE">'
            . '<strong>PROFILER ACTIVE</strong>'
            . '<span>' . p7ops_profiler_visible_escape($path) . '</span>'
            . '<span>' . p7ops_profiler_visible_escape($duration) . ' ms</span>'
            . '<a href="' . p7ops_profiler_visible_escape($primaryHref) . '">' . p7ops_profiler_visible_escape($primaryLabel) . '</a>'
            . '<a href="/opus-lstsar-manager/profiler/exit?next=' . p7ops_profiler_visible_escape(rawurlencode($uri)) . '">Exit</a>'
            . '</div>';
    }
}
PHP;

$pattern = "~if \(!function_exists\('p7ops_profiler_visible_badge_html'\)\) \{\s*function p7ops_profiler_visible_badge_html\(\): string\s*\{.*?\n    \}\n\}~s";
if (preg_match($pattern, $language)) {
    $language = preg_replace($pattern, $newBadge, $language, 1) ?: $language;
}

p7open_write($languageFile, $language);

$router = p7open_read($routerFile);
if (!str_contains($router, 'p7ops_profiler_context_store_app_uri();')) {
    if (str_contains($router, 'p7ops_profiler_visible_boot_once();')) {
        $router = str_replace(
            'p7ops_profiler_visible_boot_once();',
            'p7ops_profiler_visible_boot_once();' . PHP_EOL . 'p7ops_profiler_context_store_app_uri();',
            $router
        );
    } elseif (str_contains($router, 'p7ops_unified_navigation_boot_once();')) {
        $router = str_replace(
            'p7ops_unified_navigation_boot_once();',
            'p7ops_unified_navigation_boot_once();' . PHP_EOL . 'p7ops_profiler_context_store_app_uri();',
            $router
        );
    } else {
        $router = str_replace(
            "require_once __DIR__ . '/language.php';",
            "require_once __DIR__ . '/language.php';" . PHP_EOL . 'p7ops_profiler_context_store_app_uri();',
            $router
        );
    }
}
if (!str_contains($router, 'P7_OPS_PROFILER_OPEN_CONTEXT_CORE')) {
    $router = str_replace('<?php', "<?php\n/** P7_OPS_PROFILER_OPEN_CONTEXT_CORE */", $router);
}
p7open_write($routerFile, $router);

$profiler = p7open_read($profilerFile);
if (!str_contains($profiler, 'P7_OPS_PROFILER_OPEN_CONTEXT_CORE')) {
    $profiler = str_replace(
        '<main class="opf-page" data-contract="P7_OPS_PROFILER_CHAIN_CLEANUP_CORE">',
        '<main class="opf-page" data-contract="P7_OPS_PROFILER_CHAIN_CLEANUP_CORE P7_OPS_PROFILER_OPEN_CONTEXT_CORE">',
        $profiler
    );

    $profiler = str_replace(
        '<a href="/opus-lstsar-manager?site=site-alpha&lang=fr&profiler=1">Enable</a>',
        '<a href="<?= $e(p7ops_profiler_context_last_app_uri()) ?>">Back to app</a><a href="/opus-lstsar-manager/profiler">Refresh profiler</a>',
        $profiler
    );
}
p7open_write($profilerFile, $profiler);

$readme = is_file($readmeFile) ? p7open_read($readmeFile) : '# OPUS P7 OPS' . PHP_EOL;
if (!str_contains($readme, 'P7_OPS_PROFILER_OPEN_CONTEXT_CORE')) {
    $readme .= PHP_EOL;
    $readme .= '## P7_OPS_PROFILER_OPEN_CONTEXT_CORE' . PHP_EOL . PHP_EOL;
    $readme .= '- Stores the last non-profiler application URL in session.' . PHP_EOL;
    $readme .= '- On profiler pages, the visible profiler ribbon shows `Back to app` instead of a no-op `Open profiler` link.' . PHP_EOL;
    $readme .= '- On application pages, the same ribbon still opens `/opus-lstsar-manager/profiler`.' . PHP_EOL;
}
p7open_write($readmeFile, $readme);

echo 'P7_OPS_PROFILER_OPEN_CONTEXT_CORE_UPDATED' . PHP_EOL;
