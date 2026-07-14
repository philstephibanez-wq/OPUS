<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';
$publicRoot = $siteRoot . DIRECTORY_SEPARATOR . 'www';
$runtimeStore = $siteRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'local-users.json';
$registryDatabaseRelative = 'var/registry/owasys-runtime-http-smoke.sqlite';
$registryDatabase = $siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $registryDatabaseRelative);
$tmpRoot = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'owasys-runtime-http-smoke';
$router = $tmpRoot . DIRECTORY_SEPARATOR . 'router.php';
$backup = $tmpRoot . DIRECTORY_SEPARATOR . 'local-users.backup.json';

if (!function_exists('proc_open')) {
    fwrite(STDERR, "OWASYS_RUNTIME_FSM_HTTP_PROC_OPEN_UNAVAILABLE\n");
    exit(1);
}
if (!class_exists(SQLite3::class)) {
    fwrite(STDERR, "OWASYS_RUNTIME_FSM_HTTP_SQLITE3_EXTENSION_MISSING\n");
    exit(1);
}

function owasys_runtime_fsm_http_remove_tree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
}

function owasys_runtime_fsm_http_remove_registry_database(string $databasePath): void
{
    @unlink($databasePath);
    @unlink($databasePath . '-shm');
    @unlink($databasePath . '-wal');
    $registryDir = dirname($databasePath);
    if (is_dir($registryDir) && count(scandir($registryDir) ?: []) === 2) {
        @rmdir($registryDir);
    }
}

function owasys_runtime_fsm_http_status(array $headers): int
{
    foreach ($headers as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $match) === 1) {
            return (int) $match[1];
        }
    }
    return 0;
}

function owasys_runtime_fsm_http_header(array $headers, string $name): ?string
{
    $prefix = strtolower($name) . ':';
    foreach ($headers as $header) {
        if (str_starts_with(strtolower($header), $prefix)) {
            return trim(substr($header, strlen($prefix)));
        }
    }
    return null;
}

function owasys_runtime_fsm_http_cookie(array $headers, string $existingCookie): string
{
    $cookies = [];
    if ($existingCookie !== '') {
        foreach (explode(';', $existingCookie) as $cookie) {
            $pair = trim($cookie);
            if ($pair !== '' && str_contains($pair, '=')) {
                [$name, $value] = explode('=', $pair, 2);
                $cookies[$name] = $value;
            }
        }
    }
    foreach ($headers as $header) {
        if (!str_starts_with(strtolower($header), 'set-cookie:')) {
            continue;
        }
        $pair = explode(';', trim(substr($header, strlen('set-cookie:'))), 2)[0];
        if ($pair !== '' && str_contains($pair, '=')) {
            [$name, $value] = explode('=', $pair, 2);
            $cookies[$name] = $value;
        }
    }
    $pairs = [];
    foreach ($cookies as $name => $value) {
        $pairs[] = $name . '=' . $value;
    }
    return implode('; ', $pairs);
}

function owasys_runtime_fsm_http_request(string $baseUrl, string $method, string $path, string $body = '', string $cookie = ''): array
{
    $headers = ['Connection: close'];
    if ($body !== '') {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $headers[] = 'Content-Length: ' . strlen($body);
    }
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }
    $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body, 'ignore_errors' => true, 'follow_location' => 0, 'max_redirects' => 0, 'timeout' => 5]]);
    $responseBody = @file_get_contents($baseUrl . $path, false, $context);
    $headersOut = is_array($http_response_header ?? null) ? $http_response_header : [];
    return [
        'status' => owasys_runtime_fsm_http_status($headersOut),
        'location' => owasys_runtime_fsm_http_header($headersOut, 'Location'),
        'cookie' => owasys_runtime_fsm_http_cookie($headersOut, $cookie),
        'body' => is_string($responseBody) ? $responseBody : '',
    ];
}

function owasys_runtime_fsm_http_assert_redirect(array $response, string $expectedLocation, string $error): void
{
    if (($response['status'] ?? 0) !== 303 || ($response['location'] ?? '') !== $expectedLocation) {
        fwrite(STDERR, $error . ': status=' . (string) ($response['status'] ?? 0) . ' location=' . (string) ($response['location'] ?? '') . "\n");
        exit(1);
    }
}

function owasys_runtime_fsm_http_sqlite_value(string $databasePath, string $sql): mixed
{
    $db = new SQLite3($databasePath);
    try {
        return $db->querySingle($sql);
    } finally {
        $db->close();
    }
}

foreach ([$siteRoot, $publicRoot] as $requiredDir) {
    if (!is_dir($requiredDir)) {
        fwrite(STDERR, "OWASYS_RUNTIME_FSM_HTTP_REQUIRED_DIR_MISSING: {$requiredDir}\n");
        exit(1);
    }
}

owasys_runtime_fsm_http_remove_tree($tmpRoot);
owasys_runtime_fsm_http_remove_registry_database($registryDatabase);
if (!is_dir($tmpRoot) && !mkdir($tmpRoot, 0775, true) && !is_dir($tmpRoot)) {
    fwrite(STDERR, "OWASYS_RUNTIME_FSM_HTTP_TMP_CREATE_FAILED\n");
    exit(1);
}

$hadRuntimeStore = is_file($runtimeStore);
if ($hadRuntimeStore && !copy($runtimeStore, $backup)) {
    fwrite(STDERR, "OWASYS_RUNTIME_FSM_HTTP_BACKUP_FAILED\n");
    exit(1);
}

$server = null;
$pipes = [];
$port = 19080 + random_int(0, 900);
$baseUrl = 'http://127.0.0.1:' . $port;

try {
    $runtimeStoreDir = dirname($runtimeStore);
    if (!is_dir($runtimeStoreDir) && !mkdir($runtimeStoreDir, 0775, true) && !is_dir($runtimeStoreDir)) {
        throw new RuntimeException('OWASYS_RUNTIME_FSM_HTTP_AUTH_DIR_CREATE_FAILED');
    }
    $password = 'OwasysHttpSmokePass123!';
    $store = ['contract' => 'OWASYS_LOCAL_USER_STORE_V1', 'committed' => false, 'users' => ['fsm-http' => ['id' => 'fsm-http', 'label' => 'FSM HTTP Smoke', 'profile' => 'dev', 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'must_change_password' => false]]];
    $encodedStore = json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($encodedStore) || file_put_contents($runtimeStore, $encodedStore . "\n") === false) {
        throw new RuntimeException('OWASYS_RUNTIME_FSM_HTTP_AUTH_STORE_WRITE_FAILED');
    }

    $publicRootExport = var_export(str_replace('\\', '/', $publicRoot), true);
    $registryDatabaseExport = var_export($registryDatabaseRelative, true);
    $routerSource = <<<'PHP'
<?php
declare(strict_types=1);

$publicRoot = __PUBLIC_ROOT__;
$owasysRegistryDatabaseRelative = __REGISTRY_DATABASE__;
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = is_string($path) ? rawurldecode($path) : '/';
$path = '/' . ltrim($path, '/');
$staticFile = realpath($publicRoot . $path);
$publicRootReal = realpath($publicRoot);
if ($staticFile !== false && $publicRootReal !== false && str_starts_with(str_replace('\\', '/', $staticFile), str_replace('\\', '/', $publicRootReal) . '/') && is_file($staticFile)) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $publicRoot . '/index.php';
require $publicRoot . '/index.php';
return true;
PHP;
    $routerSource = str_replace(['__PUBLIC_ROOT__', '__REGISTRY_DATABASE__'], [$publicRootExport, $registryDatabaseExport], $routerSource);
    if (file_put_contents($router, $routerSource) === false) {
        throw new RuntimeException('OWASYS_RUNTIME_FSM_HTTP_ROUTER_WRITE_FAILED');
    }

    $command = PHP_BINARY . ' -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($publicRoot) . ' ' . escapeshellarg($router);
    $server = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (!is_resource($server)) {
        throw new RuntimeException('OWASYS_RUNTIME_FSM_HTTP_SERVER_START_FAILED');
    }
    $ready = false;
    for ($attempt = 0; $attempt < 30; $attempt++) {
        if (is_string(@file_get_contents($baseUrl . '/login'))) {
            $ready = true;
            break;
        }
        usleep(100000);
    }
    if (!$ready) {
        throw new RuntimeException('OWASYS_RUNTIME_FSM_HTTP_SERVER_NOT_READY');
    }

    $login = owasys_runtime_fsm_http_request($baseUrl, 'POST', '/login', http_build_query(['owasys_action' => 'password-signin', 'owasys_username' => 'fsm-http', 'owasys_password' => $password]));
    owasys_runtime_fsm_http_assert_redirect($login, '/', 'OWASYS_RUNTIME_FSM_HTTP_LOGIN_REDIRECT_INVALID');
    $cookie = (string) ($login['cookie'] ?? '');
    if ($cookie === '') {
        throw new RuntimeException('OWASYS_RUNTIME_FSM_HTTP_LOGIN_COOKIE_MISSING');
    }

    $structureWithoutApp = owasys_runtime_fsm_http_request($baseUrl, 'GET', '/structure', '', $cookie);
    owasys_runtime_fsm_http_assert_redirect($structureWithoutApp, '/applications', 'OWASYS_RUNTIME_FSM_HTTP_STRUCTURE_GUARD_REDIRECT_INVALID');

    $registry = owasys_runtime_fsm_http_request($baseUrl, 'GET', '/applications', '', $cookie);
    if (($registry['status'] ?? 0) !== 200 || !str_contains((string) ($registry['body'] ?? ''), 'OWASYS_REGISTRY_APP_TREE')) {
        throw new RuntimeException('OWASYS_RUNTIME_FSM_HTTP_REGISTRY_RENDER_INVALID');
    }
    if (!is_file($registryDatabase)) {
        throw new RuntimeException('OWASYS_RUNTIME_FSM_HTTP_REGISTRY_DATABASE_MISSING');
    }
    $cookie = (string) ($registry['cookie'] ?? $cookie);

    $select = owasys_runtime_fsm_http_request($baseUrl, 'POST', '/applications', http_build_query(['owasys_action' => 'select-app', 'owasys_app_id' => 'demo-app']), $cookie);
    owasys_runtime_fsm_http_assert_redirect($select, '/structure', 'OWASYS_RUNTIME_FSM_HTTP_SELECT_APP_REDIRECT_INVALID');
    $cookie = (string) ($select['cookie'] ?? $cookie);
    $currentAppJson = (string) owasys_runtime_fsm_http_sqlite_value($registryDatabase, "SELECT value_json FROM owasys_runtime_context WHERE key = 'current_app'");
    if (!str_contains($currentAppJson, 'demo-app') || (int) owasys_runtime_fsm_http_sqlite_value($registryDatabase, "SELECT COUNT(*) FROM owasys_application_events WHERE event_type = 'select_app'") !== 1) {
        throw new RuntimeException('OWASYS_RUNTIME_FSM_HTTP_CONTEXT_SELECT_NOT_PERSISTED');
    }

    $structure = owasys_runtime_fsm_http_request($baseUrl, 'GET', '/structure', '', $cookie);
    if (($structure['status'] ?? 0) !== 200 || !str_contains((string) ($structure['body'] ?? ''), 'OWASYS_CURRENT_APP_CONTEXT')) {
        throw new RuntimeException('OWASYS_RUNTIME_FSM_HTTP_STRUCTURE_RENDER_INVALID');
    }

    $logout = owasys_runtime_fsm_http_request($baseUrl, 'GET', '/logout', '', $cookie);
    owasys_runtime_fsm_http_assert_redirect($logout, '/login', 'OWASYS_RUNTIME_FSM_HTTP_LOGOUT_REDIRECT_INVALID');
    if ((int) owasys_runtime_fsm_http_sqlite_value($registryDatabase, "SELECT COUNT(*) FROM owasys_runtime_context WHERE key = 'current_app'") !== 0) {
        throw new RuntimeException('OWASYS_RUNTIME_FSM_HTTP_CONTEXT_LOGOUT_NOT_CLEARED');
    }
    if ((int) owasys_runtime_fsm_http_sqlite_value($registryDatabase, "SELECT COUNT(*) FROM owasys_application_events WHERE event_type = 'logout'") !== 1) {
        throw new RuntimeException('OWASYS_RUNTIME_FSM_HTTP_LOGOUT_EVENT_NOT_PERSISTED');
    }
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        proc_close($server);
    }
    if ($hadRuntimeStore && is_file($backup)) {
        @copy($backup, $runtimeStore);
    } elseif (!$hadRuntimeStore && is_file($runtimeStore)) {
        @unlink($runtimeStore);
        $authDir = dirname($runtimeStore);
        if (is_dir($authDir) && count(scandir($authDir) ?: []) === 2) {
            @rmdir($authDir);
        }
    }
    owasys_runtime_fsm_http_remove_registry_database($registryDatabase);
    owasys_runtime_fsm_http_remove_tree($tmpRoot);
}

if (file_exists($tmpRoot)) {
    fwrite(STDERR, "OWASYS_RUNTIME_FSM_HTTP_TMP_CLEANUP_FAILED\n");
    exit(1);
}
if (is_file($registryDatabase)) {
    fwrite(STDERR, "OWASYS_RUNTIME_FSM_HTTP_REGISTRY_CLEANUP_FAILED\n");
    exit(1);
}

echo "OWASYS_RUNTIME_FSM_HTTP_SMOKE_OK\n";
