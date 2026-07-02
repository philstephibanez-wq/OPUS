<?php
declare(strict_types=1);

$root = getcwd();
$publicDir = $root . '/sites/opus-p7-ops/public';
$languageFile = $publicDir . '/language.php';
$routerFile = $publicDir . '/router.php';
$cssFile = $publicDir . '/ops-ui.css';
$readmeFile = $root . '/sites/opus-p7-ops/README.md';
$logDir = $root . '/var/logs/opus_lstsar-manager';

foreach ([$languageFile, $routerFile, $cssFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, 'P7_OPS_PROFESSIONAL_PATCH_TARGET_MISSING: ' . $file . PHP_EOL);
        exit(1);
    }
}

if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
    fwrite(STDERR, 'P7_OPS_LOG_DIR_CREATE_FAILED' . PHP_EOL);
    exit(1);
}

if (!is_dir($root . '/var/logs') && !mkdir($root . '/var/logs', 0775, true) && !is_dir($root . '/var/logs')) {
    fwrite(STDERR, 'P7_OPS_LOG_PARENT_CREATE_FAILED' . PHP_EOL);
    exit(1);
}

file_put_contents($root . '/var/logs/.gitignore', "*.log\n**/*.log\n!**/.gitkeep\n");
file_put_contents($logDir . '/.gitkeep', '');

$source = file_get_contents($languageFile);
if ($source === false) {
    fwrite(STDERR, 'P7_OPS_LANGUAGE_READ_FAILED' . PHP_EOL);
    exit(1);
}

$additions = json_decode(<<<'JSON'
{
  "Navigation unifiée": "Unified navigation",
  "Navigation unifiee": "Unified navigation",
  "Dashboard, Operations, Command Center, Navigation et Actions utilisent maintenant les mêmes routes OPS locales.": "Dashboard, Operations, Command Center, Navigation and Actions now use the same local OPS routes.",
  "utilisent maintenant les mêmes routes OPS locales.": "now use the same local OPS routes.",
  "les mêmes routes OPS locales": "the same local OPS routes",
  "mêmes routes OPS locales": "same local OPS routes",
  "et Actions": "and Actions",
  "Table détaillée avec source/destination résumées. Les structures longues sont wrappées et confinées dans le panel.": "Detailed table with summarized source/destination. Long structures are wrapped and confined in the panel.",
  "Table détaillée avec source/destination résumées.": "Detailed table with summarized source/destination.",
  "Les structures longues sont wrappées et confinées dans le panel.": "Long structures are wrapped and confined in the panel.",
  "Table détaillée": "Detailed table",
  "source/destination résumées": "summarized source/destination",
  "résumées": "summarized",
  "wrappées": "wrapped",
  "confinées": "confined",
  "structures longues": "long structures",
  "Compteurs OPS": "OPS counters",
  "Synthèse": "Summary",
  "Prochaines étapes": "Next steps",
  "Opérations": "Operations",
  "Opération": "Operation",
  "Statut": "Status",
  "Aperçu": "Preview",
  "Simulation": "Dry run",
  "Ouvrir": "Open",
  "Exécuter": "Run",
  "Détails": "Details",
  "Contrôles": "Checks",
  "Fichiers": "Files",
  "Avertissement": "Warning",
  "Erreur": "Error",
  "Actif": "Active",
  "Prêt": "Ready",
  "Bloqué": "Blocked",
  "prêt": "ready",
  "bloqué": "blocked"
}
JSON, true);

if (!is_array($additions)) {
    fwrite(STDERR, 'P7_OPS_EN_ADDITIONS_INVALID' . PHP_EOL);
    exit(1);
}

$pattern = "/(function p7ops_i18n_page_translation_dictionary\(\): array\s*\{.*?json_decode\(<<<'JSON'\R)(.*?)(\RJSON, true\);)/s";
if (!preg_match($pattern, $source, $match)) {
    fwrite(STDERR, 'P7_OPS_PAGE_TRANSLATION_DICTIONARY_BLOCK_NOT_FOUND' . PHP_EOL);
    exit(1);
}

$dictionary = json_decode($match[2], true);
if (!is_array($dictionary)) {
    fwrite(STDERR, 'P7_OPS_PAGE_TRANSLATION_DICTIONARY_JSON_INVALID' . PHP_EOL);
    exit(1);
}

$current = isset($dictionary['en']) && is_array($dictionary['en']) ? $dictionary['en'] : [];
$dictionary['en'] = array_replace($current, $additions);

$json = json_encode($dictionary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if (!is_string($json)) {
    fwrite(STDERR, 'P7_OPS_PAGE_TRANSLATION_DICTIONARY_JSON_ENCODE_FAILED' . PHP_EOL);
    exit(1);
}

$source = preg_replace($pattern, '$1' . $json . '$3', $source, 1, $count);
if ($count !== 1 || !is_string($source)) {
    fwrite(STDERR, 'P7_OPS_PAGE_TRANSLATION_DICTIONARY_REPLACE_FAILED' . PHP_EOL);
    exit(1);
}

$loggingProfilerBlock = <<<'PHP'

/** P7_OPS_PROFILER_AND_ACCESS_LOG_CORE */
if (!function_exists('p7ops_log_root')) {
    function p7ops_log_root(): string
    {
        return dirname(__DIR__, 3) . '/var/logs/opus_lstsar-manager';
    }
}

if (!function_exists('p7ops_ensure_log_root')) {
    function p7ops_ensure_log_root(): void
    {
        $root = p7ops_log_root();
        if (!is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
            throw new RuntimeException('P7_OPS_LOG_DIR_CREATE_FAILED: ' . $root);
        }
    }
}

if (!function_exists('p7ops_log_line')) {
    function p7ops_log_line(string $filename, array $payload): void
    {
        p7ops_ensure_log_root();

        $line = json_encode(array_merge([
            'ts' => gmdate('c'),
            'app' => 'opus_lstsar-manager',
        ], $payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($line)) {
            $line = '{"ts":"' . gmdate('c') . '","app":"opus_lstsar-manager","level":"ERROR","event":"json_encode_failed"}';
        }

        file_put_contents(p7ops_log_root() . '/' . $filename, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('p7ops_access_log_once')) {
    function p7ops_access_log_once(): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $done = true;

        try {
            $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
            p7ops_log_line('access.log', [
                'level' => 'INFO',
                'event' => 'http_request',
                'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
                'uri' => $uri,
                'path' => rawurldecode((string) (parse_url($uri, PHP_URL_PATH) ?: '')),
                'query' => (string) (parse_url($uri, PHP_URL_QUERY) ?: ''),
                'remote_addr' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ]);
        } catch (Throwable $exception) {
            error_log('P7_OPS_ACCESS_LOG_FAILED: ' . $exception->getMessage());
        }
    }
}

if (!function_exists('p7ops_profiler_start_once')) {
    function p7ops_profiler_start_once(): void
    {
        static $started = false;
        if ($started) {
            return;
        }

        $started = true;
        $GLOBALS['p7ops_profiler_start_microtime'] = microtime(true);
        $GLOBALS['p7ops_profiler_start_memory'] = memory_get_usage(true);

        register_shutdown_function(static function (): void {
            p7ops_profiler_finish_once('shutdown');
        });
    }
}

if (!function_exists('p7ops_profiler_finish_once')) {
    function p7ops_profiler_finish_once(string $phase = 'finish'): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        $done = true;

        try {
            $start = (int) ($GLOBALS['p7ops_profiler_start_microtime'] ?? hrtime(true));
            $durationMs = round((microtime(true) - $start) * 1000, 3);
            $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');

            p7ops_log_line('profiler.log', [
                'level' => 'INFO',
                'event' => 'profile_request',
                'phase' => $phase,
                'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
                'uri' => $uri,
                'path' => rawurldecode((string) (parse_url($uri, PHP_URL_PATH) ?: '')),
                'status' => http_response_code() ?: 200,
                'duration_ms' => $durationMs,
                'memory_start_bytes' => (int) ($GLOBALS['p7ops_profiler_start_memory'] ?? 0),
                'memory_peak_bytes' => memory_get_peak_usage(true),
            ]);
        } catch (Throwable $exception) {
            error_log('P7_OPS_PROFILER_LOG_FAILED: ' . $exception->getMessage());
        }
    }
}
PHP;

if (!str_contains($source, 'P7_OPS_PROFILER_AND_ACCESS_LOG_CORE')) {
    $needle = 'p7ops_i18n_begin();';
    if (str_contains($source, $needle)) {
        $source = str_replace($needle, $loggingProfilerBlock . PHP_EOL . PHP_EOL . $needle, $source);
    } else {
        $source .= PHP_EOL . $loggingProfilerBlock . PHP_EOL;
    }
}

if (!str_contains($source, 'P7_OPS_EN_NAVIGATION_AND_PROFESSIONAL_TEXT_CORE')) {
    $source = str_replace(
        'P7_OPS_I18N_VISIBLE_STRINGS_FIX_CORE / completed visible labels',
        'P7_OPS_EN_NAVIGATION_AND_PROFESSIONAL_TEXT_CORE / English navigation leak lock and professional text length / P7_OPS_I18N_VISIBLE_STRINGS_FIX_CORE / completed visible labels',
        $source
    );
}

if (file_put_contents($languageFile, $source) === false) {
    fwrite(STDERR, 'P7_OPS_LANGUAGE_WRITE_FAILED' . PHP_EOL);
    exit(1);
}

$router = file_get_contents($routerFile);
if ($router === false) {
    fwrite(STDERR, 'P7_OPS_ROUTER_READ_FAILED' . PHP_EOL);
    exit(1);
}

$require = "require_once __DIR__ . '/language.php';";
if (!str_contains($router, $require)) {
    fwrite(STDERR, 'P7_OPS_ROUTER_LANGUAGE_REQUIRE_MISSING' . PHP_EOL);
    exit(1);
}

if (!str_contains($router, 'p7ops_access_log_once();')) {
    $router = str_replace($require, $require . PHP_EOL . 'p7ops_access_log_once();', $router);
}

if (!str_contains($router, 'p7ops_profiler_start_once();')) {
    $router = str_replace('p7ops_access_log_once();', 'p7ops_access_log_once();' . PHP_EOL . 'p7ops_profiler_start_once();', $router);
}

if (!str_contains($router, 'P7_OPS_PROFILER_AND_ACCESS_LOG_CORE')) {
    $router = str_replace("<?php\n", "<?php\n/** P7_OPS_PROFILER_AND_ACCESS_LOG_CORE */\n", $router);
}

if (file_put_contents($routerFile, $router) === false) {
    fwrite(STDERR, 'P7_OPS_ROUTER_WRITE_FAILED' . PHP_EOL);
    exit(1);
}

$css = file_get_contents($cssFile);
if ($css === false) {
    fwrite(STDERR, 'P7_OPS_CSS_READ_FAILED' . PHP_EOL);
    exit(1);
}

if (!str_contains($css, 'P7_OPS_PROFESSIONAL_TEXT_LENGTH_CORE')) {
    $css .= PHP_EOL;
    $css .= '/* P7_OPS_PROFESSIONAL_TEXT_LENGTH_CORE */' . PHP_EOL;
    $css .= '.ops-card,.ops-panel,.ops-section,.ops-shell,.ops-table-wrap{min-width:0;overflow:hidden}' . PHP_EOL;
    $css .= '.ops-table{width:100%;table-layout:fixed;border-collapse:collapse}' . PHP_EOL;
    $css .= '.ops-table th{white-space:nowrap;overflow-wrap:normal;word-break:normal}' . PHP_EOL;
    $css .= '.ops-table td{vertical-align:top;overflow-wrap:break-word;word-break:normal}' . PHP_EOL;
    $css .= '.ops-table code,.ops-card code,.ops-panel code,.ops-kv code,.ops-value,td code{white-space:normal;overflow-wrap:anywhere;word-break:break-word}' . PHP_EOL;
    $css .= '.ops-kv,.ops-kv-grid,.ops-summary-grid,.ops-source-summary,.ops-destination-summary,[class*="summary"]{min-width:0}' . PHP_EOL;
    $css .= '.ops-kv-grid,.ops-summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(11rem,1fr));gap:.65rem}' . PHP_EOL;
    $css .= '.ops-kv__label,.ops-field-label,.ops-label,dt,[class*="label"]{white-space:nowrap;overflow-wrap:normal;word-break:normal;max-width:100%}' . PHP_EOL;
    $css .= '.ops-kv__value,.ops-field-value,dd,[class*="value"]{min-width:0;overflow-wrap:anywhere;word-break:break-word}' . PHP_EOL;
    $css .= '.ops-badge,.ops-pill,.ops-action-button,.ops-table .status{white-space:nowrap}' . PHP_EOL;
    $css .= '@media (max-width:900px){.ops-table{table-layout:auto}.ops-kv-grid,.ops-summary-grid{grid-template-columns:repeat(auto-fit,minmax(9rem,1fr))}}' . PHP_EOL;
}

if (file_put_contents($cssFile, $css) === false) {
    fwrite(STDERR, 'P7_OPS_CSS_WRITE_FAILED' . PHP_EOL);
    exit(1);
}

$readme = is_file($readmeFile) ? file_get_contents($readmeFile) : '# OPUS P7 OPS' . PHP_EOL;
if (!is_string($readme)) {
    $readme = '# OPUS P7 OPS' . PHP_EOL;
}

if (!str_contains($readme, 'P7_OPS_EN_NAVIGATION_AND_PROFESSIONAL_TEXT_CORE')) {
    $readme .= PHP_EOL;
    $readme .= '## P7_OPS_EN_NAVIGATION_AND_PROFESSIONAL_TEXT_CORE' . PHP_EOL . PHP_EOL;
    $readme .= '- Locks English navigation pages against remaining French UI fragments.' . PHP_EOL;
    $readme .= '- Adds professional text-length handling for tables, key/value cards and technical identifiers.' . PHP_EOL;
    $readme .= '- Keeps technical values such as operation names, DSNs, model names and table names unchanged.' . PHP_EOL;
    $readme .= '- Covered by `tools/smokes/smoke_p7_ops_en_navigation_profiler_text_core.php`.' . PHP_EOL;
}

if (!str_contains($readme, 'P7_OPS_PROFILER_AND_ACCESS_LOG_CORE')) {
    $readme .= PHP_EOL;
    $readme .= '## P7_OPS_PROFILER_AND_ACCESS_LOG_CORE' . PHP_EOL . PHP_EOL;
    $readme .= '- Adds `var/logs/opus_lstsar-manager/access.log` with one JSON line per routed request.' . PHP_EOL;
    $readme .= '- Adds `var/logs/opus_lstsar-manager/profiler.log` with duration, status and peak memory per routed request.' . PHP_EOL;
    $readme .= '- The app cannot log `ERR_CONNECTION_REFUSED` because no PHP process receives that request.' . PHP_EOL;
}

if (file_put_contents($readmeFile, $readme) === false) {
    fwrite(STDERR, 'P7_OPS_README_WRITE_FAILED' . PHP_EOL);
    exit(1);
}

$oldSmoke = $root . '/tools/smokes/smoke_p7_ops_i18n_en_visible_leak_lock_core.php';
if (is_file($oldSmoke)) {
    $old = file_get_contents($oldSmoke);
    if (is_string($old) && !str_contains($old, 'P7_OPS_SMOKE_OUTPUT_BUFFER_HEADER_LOCK')) {
        $old = str_replace(
            "declare(strict_types=1);\n",
            "declare(strict_types=1);\n\n/* P7_OPS_SMOKE_OUTPUT_BUFFER_HEADER_LOCK */\nob_start();\n",
            $old
        );
        $old = str_replace(
            "echo 'P7_OPS_I18N_EN_VISIBLE_LEAK_LOCK_CORE_SMOKE_OK' . PHP_EOL;",
            "echo 'P7_OPS_I18N_EN_VISIBLE_LEAK_LOCK_CORE_SMOKE_OK' . PHP_EOL;\nif (ob_get_level() > 0) { ob_end_flush(); }",
            $old
        );
        file_put_contents($oldSmoke, $old);
    }
}

echo 'P7_OPS_EN_NAVIGATION_PROFILER_TEXT_CORE_UPDATED' . PHP_EOL;
