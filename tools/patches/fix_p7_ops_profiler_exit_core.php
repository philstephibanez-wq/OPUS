<?php
declare(strict_types=1);

$root = getcwd();
$publicDir = $root . '/sites/opus-p7-ops/public';
$siteDir = $root . '/sites/opus-p7-ops';

function p7_exit_read(string $file): string
{
    $source = file_get_contents($file);
    if ($source === false) {
        fwrite(STDERR, 'P7_PROFILER_EXIT_READ_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }

    return $source;
}

function p7_exit_write(string $file, string $source): void
{
    if (file_put_contents($file, $source) === false) {
        fwrite(STDERR, 'P7_PROFILER_EXIT_WRITE_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }
}

foreach ([$publicDir, $siteDir] as $dir) {
    if (!is_dir($dir)) {
        fwrite(STDERR, 'P7_PROFILER_EXIT_DIR_MISSING: ' . $dir . PHP_EOL);
        exit(1);
    }
}

$languageFile = $publicDir . '/language.php';
$routerFile = $publicDir . '/router.php';
$exitFile = $publicDir . '/profiler-exit.php';
$readmeFile = $siteDir . '/README.md';

foreach ([$languageFile, $routerFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, 'P7_PROFILER_EXIT_FILE_MISSING: ' . $file . PHP_EOL);
        exit(1);
    }
}

$language = p7_exit_read($languageFile);
$block = <<<'PHP'

/** P7_OPS_PROFILER_EXIT_FIX_CORE */
if (!function_exists('p7ops_profiler_url_without_profiler')) {
    function p7ops_profiler_url_without_profiler(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '/opus-lstsar-manager';
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return '/opus-lstsar-manager';
        }

        $path = (string) ($parts['path'] ?? '/opus-lstsar-manager');
        if ($path === '') {
            $path = '/opus-lstsar-manager';
        }

        $query = [];
        if (isset($parts['query']) && is_string($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        foreach (['profiler', 'profile', '_profiler'] as $key) {
            unset($query[$key]);
        }

        $cleanQuery = http_build_query($query);
        return $path . ($cleanQuery !== '' ? '?' . $cleanQuery : '');
    }
}

if (!function_exists('p7ops_profiler_disable_all_modes')) {
    function p7ops_profiler_disable_all_modes(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('OPUSLSTSAROPS');
            session_start();
        }

        foreach ([
            'p7ops_clean_profiler_enabled',
            'p7ops_clean_profiler_history',
            'p7ops_sf_profiler_enabled',
            'p7ops_sf_profiler_history',
            'p7ops_profiler_enabled',
            'p7ops_profiler_history',
        ] as $key) {
            unset($_SESSION[$key]);
        }

        session_write_close();
    }
}

if (!function_exists('p7ops_profiler_exit_path')) {
    function p7ops_profiler_exit_path(string $path): bool
    {
        return in_array($path, ['/opus-lstsar-manager/profiler/exit', '/_profiler/exit'], true);
    }
}
PHP;

if (!str_contains($language, 'P7_OPS_PROFILER_EXIT_FIX_CORE')) {
    $language .= PHP_EOL . $block . PHP_EOL;
}
p7_exit_write($languageFile, $language);

$exit = <<<'PHP'
<?php
/** P7_OPS_PROFILER_EXIT_FIX_CORE */
declare(strict_types=1);

require_once __DIR__ . '/language.php';

p7ops_profiler_disable_all_modes();

$next = p7ops_profiler_url_without_profiler((string) ($_GET['next'] ?? '/opus-lstsar-manager'));

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Location: ' . $next, true, 302);
exit;
PHP;

p7_exit_write($exitFile, $exit);

$router = p7_exit_read($routerFile);

$router = str_replace(
    'p7ops_clean_profiler_boot_once();',
    "if (!p7ops_profiler_exit_path(\$path)) {\n    p7ops_clean_profiler_boot_once();\n}",
    $router
);

$router = str_replace(
    'p7ops_unified_navigation_boot_once();',
    "if (!p7ops_profiler_exit_path(\$path)) {\n    p7ops_unified_navigation_boot_once();\n}",
    $router
);

$router = preg_replace(
    "~if \(!p7ops_profiler_exit_path\(\$path\)\) \{\s*if \(!p7ops_profiler_exit_path\(\$path\)\) \{\s*p7ops_clean_profiler_boot_once\(\);\s*\}\s*\}~s",
    "if (!p7ops_profiler_exit_path(\$path)) {\n    p7ops_clean_profiler_boot_once();\n}",
    $router
) ?: $router;

$router = preg_replace(
    "~if \(!p7ops_profiler_exit_path\(\$path\)\) \{\s*if \(!p7ops_profiler_exit_path\(\$path\)\) \{\s*p7ops_unified_navigation_boot_once\(\);\s*\}\s*\}~s",
    "if (!p7ops_profiler_exit_path(\$path)) {\n    p7ops_unified_navigation_boot_once();\n}",
    $router
) ?: $router;

if (!str_contains($router, 'P7_OPS_PROFILER_EXIT_FIX_CORE')) {
    $router = str_replace('<?php', "<?php\n/** P7_OPS_PROFILER_EXIT_FIX_CORE */", $router);
}
p7_exit_write($routerFile, $router);

if (is_file($readmeFile)) {
    $readme = p7_exit_read($readmeFile);
} else {
    $readme = '# OPUS P7 OPS' . PHP_EOL;
}

if (!str_contains($readme, 'P7_OPS_PROFILER_EXIT_FIX_CORE')) {
    $readme .= PHP_EOL;
    $readme .= '## P7_OPS_PROFILER_EXIT_FIX_CORE' . PHP_EOL . PHP_EOL;
    $readme .= '- Fixes profiler exit by clearing all legacy and current profiler session flags.' . PHP_EOL;
    $readme .= '- Sanitizes the redirect target by removing `profiler`, `profile` and `_profiler` query parameters.' . PHP_EOL;
    $readme .= '- Prevents profiler/navigation output buffers from starting on the profiler exit route.' . PHP_EOL;
}
p7_exit_write($readmeFile, $readme);

echo 'P7_OPS_PROFILER_EXIT_FIX_CORE_UPDATED' . PHP_EOL;
