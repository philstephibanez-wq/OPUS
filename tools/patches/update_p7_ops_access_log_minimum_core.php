<?php
declare(strict_types=1);

$root = getcwd();
$publicDir = $root . '/sites/opus-p7-ops/public';
$languageFile = $publicDir . '/language.php';
$routerFile = $publicDir . '/router.php';
$readmeFile = $root . '/sites/opus-p7-ops/README.md';

foreach ([$languageFile, $routerFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, 'P7_OPS_LOG_TARGET_MISSING: ' . $file . PHP_EOL);
        exit(1);
    }
}

$language = file_get_contents($languageFile);
if ($language === false) {
    fwrite(STDERR, 'P7_OPS_LOG_LANGUAGE_READ_FAILED' . PHP_EOL);
    exit(1);
}

$loggingBlock = <<<'PHP'

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

        $payload = array_merge([
            'ts' => gmdate('c'),
            'app' => 'opus_lstsar-manager',
        ], $payload);

        $line = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($line)) {
            $line = '{"ts":"' . gmdate('c') . '","app":"opus_lstsar-manager","level":"ERROR","event":"json_encode_failed"}';
        }

        $target = p7ops_log_root() . '/' . $filename;
        file_put_contents($target, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
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
            $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '');
            $query = (string) (parse_url($uri, PHP_URL_QUERY) ?: '');

            p7ops_log_line('access.log', [
                'level' => 'INFO',
                'event' => 'http_request',
                'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
                'uri' => $uri,
                'path' => rawurldecode($path),
                'query' => $query,
                'remote_addr' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ]);
        } catch (Throwable $exception) {
            error_log('P7_OPS_ACCESS_LOG_FAILED: ' . $exception->getMessage());
        }
    }
}
PHP;

if (!str_contains($language, 'P7_OPS_ACCESS_LOG_MINIMUM_CORE') && !str_contains($language, 'function p7ops_access_log_once')) {
    $insert = PHP_EOL . '/** P7_OPS_ACCESS_LOG_MINIMUM_CORE */' . PHP_EOL . $loggingBlock . PHP_EOL;
    $needle = 'p7ops_i18n_begin();';
    if (str_contains($language, $needle)) {
        $language = str_replace($needle, $insert . PHP_EOL . $needle, $language);
    } else {
        $language .= $insert;
    }
} elseif (!str_contains($language, 'P7_OPS_ACCESS_LOG_MINIMUM_CORE')) {
    $language = str_replace('<?php', "<?php\n/** P7_OPS_ACCESS_LOG_MINIMUM_CORE */", $language);
}

if (file_put_contents($languageFile, $language) === false) {
    fwrite(STDERR, 'P7_OPS_LOG_LANGUAGE_WRITE_FAILED' . PHP_EOL);
    exit(1);
}

$router = file_get_contents($routerFile);
if ($router === false) {
    fwrite(STDERR, 'P7_OPS_LOG_ROUTER_READ_FAILED' . PHP_EOL);
    exit(1);
}

if (!str_contains($router, 'p7ops_access_log_once();')) {
    $needle = "require_once __DIR__ . '/language.php';";
    if (!str_contains($router, $needle)) {
        fwrite(STDERR, 'P7_OPS_LOG_ROUTER_LANGUAGE_REQUIRE_MISSING' . PHP_EOL);
        exit(1);
    }

    $router = str_replace($needle, $needle . PHP_EOL . 'p7ops_access_log_once();', $router);
}

if (!str_contains($router, 'P7_OPS_ACCESS_LOG_MINIMUM_CORE')) {
    $router = str_replace("<?php\n", "<?php\n/** P7_OPS_ACCESS_LOG_MINIMUM_CORE */\n", $router);
}

if (file_put_contents($routerFile, $router) === false) {
    fwrite(STDERR, 'P7_OPS_LOG_ROUTER_WRITE_FAILED' . PHP_EOL);
    exit(1);
}

$readme = is_file($readmeFile) ? file_get_contents($readmeFile) : '# OPUS P7 OPS' . PHP_EOL;
if (!is_string($readme)) {
    $readme = '# OPUS P7 OPS' . PHP_EOL;
}

if (!str_contains($readme, 'P7_OPS_ACCESS_LOG_MINIMUM_CORE')) {
    $readme .= PHP_EOL;
    $readme .= '## P7_OPS_ACCESS_LOG_MINIMUM_CORE' . PHP_EOL . PHP_EOL;
    $readme .= '- Adds a minimum JSON-lines access log for URLs reaching the OPS router.' . PHP_EOL;
    $readme .= '- Target file: `var/logs/opus_lstsar-manager/access.log`.' . PHP_EOL;
    $readme .= '- Logged fields: timestamp, event, method, URI, decoded path, query string, remote address and user agent.' . PHP_EOL;
    $readme .= '- `ERR_CONNECTION_REFUSED` cannot be logged by the app because no PHP process receives the request.' . PHP_EOL;
    $readme .= '- Covered by `tools/smokes/smoke_p7_ops_access_log_minimum_core.php`.' . PHP_EOL;
}

if (file_put_contents($readmeFile, $readme) === false) {
    fwrite(STDERR, 'P7_OPS_LOG_README_WRITE_FAILED' . PHP_EOL);
    exit(1);
}

echo 'P7_OPS_ACCESS_LOG_MINIMUM_CORE_UPDATED' . PHP_EOL;
