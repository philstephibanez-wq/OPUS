<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';
$publicRoot = $siteRoot . DIRECTORY_SEPARATOR . 'www';
$runtimeStore = $siteRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'local-users.json';
$registryDatabaseRelative = 'var/registry/owasys-source-http-smoke.sqlite';
$registryDatabase = $siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $registryDatabaseRelative);
$tmpRoot = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'owasys-source-http-smoke';
$router = $tmpRoot . DIRECTORY_SEPARATOR . 'router.php';
$backup = $tmpRoot . DIRECTORY_SEPARATOR . 'local-users.backup.json';

if (!function_exists('proc_open')) {
    fwrite(STDERR, "OWASYS_SOURCE_HTTP_PROC_OPEN_UNAVAILABLE\n");
    exit(1);
}

function owasys_source_http_remove_tree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
}

function owasys_source_http_remove_database(string $path): void
{
    @unlink($path);
    @unlink($path . '-shm');
    @unlink($path . '-wal');
}

function owasys_source_http_status(array $headers): int
{
    foreach ($headers as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $match) === 1) {
            return (int) $match[1];
        }
    }
    return 0;
}

function owasys_source_http_header(array $headers, string $name): ?string
{
    $prefix = strtolower($name) . ':';
    foreach ($headers as $header) {
        if (str_starts_with(strtolower($header), $prefix)) {
            return trim(substr($header, strlen($prefix)));
        }
    }
    return null;
}

function owasys_source_http_cookie(array $headers, string $existingCookie): string
{
    $cookies = [];
    foreach (explode(';', $existingCookie) as $cookie) {
        $pair = trim($cookie);
        if ($pair !== '' && str_contains($pair, '=')) {
            [$name, $value] = explode('=', $pair, 2);
            $cookies[$name] = $value;
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

function owasys_source_http_request(
    string $baseUrl,
    string $method,
    string $path,
    string $body = '',
    string $cookie = '',
    string $contentType = 'application/x-www-form-urlencoded'
): array {
    $headers = ['Connection: close'];
    if ($body !== '') {
        $headers[] = 'Content-Type: ' . $contentType;
        $headers[] = 'Content-Length: ' . strlen($body);
    }
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'timeout' => 5,
        ],
    ]);
    $responseBody = @file_get_contents($baseUrl . $path, false, $context);
    $headersOut = is_array($http_response_header ?? null) ? $http_response_header : [];
    return [
        'status' => owasys_source_http_status($headersOut),
        'location' => owasys_source_http_header($headersOut, 'Location'),
        'cookie' => owasys_source_http_cookie($headersOut, $cookie),
        'body' => is_string($responseBody) ? $responseBody : '',
    ];
}

function owasys_source_http_json_request(string $baseUrl, string $path, array $payload, string $cookie = ''): array
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($body)) {
        throw new RuntimeException('OWASYS_SOURCE_HTTP_JSON_ENCODE_FAILED');
    }
    return owasys_source_http_request($baseUrl, 'POST', $path, $body, $cookie, 'application/json');
}

owasys_source_http_remove_tree($tmpRoot);
owasys_source_http_remove_database($registryDatabase);
if (!is_dir($tmpRoot) && !mkdir($tmpRoot, 0775, true) && !is_dir($tmpRoot)) {
    fwrite(STDERR, "OWASYS_SOURCE_HTTP_TMP_CREATE_FAILED\n");
    exit(1);
}

$hadRuntimeStore = is_file($runtimeStore);
if ($hadRuntimeStore && !copy($runtimeStore, $backup)) {
    fwrite(STDERR, "OWASYS_SOURCE_HTTP_AUTH_BACKUP_FAILED\n");
    exit(1);
}

$server = null;
$pipes = [];
$port = 20000 + random_int(0, 900);
$baseUrl = 'http://127.0.0.1:' . $port;

try {
    $runtimeStoreDir = dirname($runtimeStore);
    if (!is_dir($runtimeStoreDir) && !mkdir($runtimeStoreDir, 0775, true) && !is_dir($runtimeStoreDir)) {
        throw new RuntimeException('OWASYS_SOURCE_HTTP_AUTH_DIR_CREATE_FAILED');
    }

    $password = 'OwasysSourceHttp123!';
    $store = [
        'contract' => 'OWASYS_LOCAL_USER_STORE_V1',
        'committed' => false,
        'users' => [
            'source-http' => [
                'id' => 'source-http',
                'label' => 'Source HTTP Smoke',
                'profile' => 'dev',
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'must_change_password' => false,
            ],
        ],
    ];
    $encodedStore = json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($encodedStore) || file_put_contents($runtimeStore, $encodedStore . "\n") === false) {
        throw new RuntimeException('OWASYS_SOURCE_HTTP_AUTH_STORE_WRITE_FAILED');
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
    $routerSource = str_replace(
        ['__PUBLIC_ROOT__', '__REGISTRY_DATABASE__'],
        [$publicRootExport, $registryDatabaseExport],
        $routerSource
    );
    if (file_put_contents($router, $routerSource) === false) {
        throw new RuntimeException('OWASYS_SOURCE_HTTP_ROUTER_WRITE_FAILED');
    }

    $command = PHP_BINARY . ' -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($publicRoot) . ' ' . escapeshellarg($router);
    $server = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (!is_resource($server)) {
        throw new RuntimeException('OWASYS_SOURCE_HTTP_SERVER_START_FAILED');
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
        throw new RuntimeException('OWASYS_SOURCE_HTTP_SERVER_NOT_READY');
    }

    $unauthorized = owasys_source_http_json_request($baseUrl, '/source-action.php', ['action' => 'list']);
    if (($unauthorized['status'] ?? 0) !== 401 || !str_contains((string) ($unauthorized['body'] ?? ''), 'OWASYS_SOURCE_AUTH_REQUIRED')) {
        throw new RuntimeException('OWASYS_SOURCE_HTTP_UNAUTHORIZED_GUARD_INVALID');
    }

    $login = owasys_source_http_request(
        $baseUrl,
        'POST',
        '/login',
        http_build_query([
            'owasys_action' => 'password-signin',
            'owasys_username' => 'source-http',
            'owasys_password' => $password,
        ])
    );
    if (($login['status'] ?? 0) !== 303 || ($login['location'] ?? '') !== '/') {
        throw new RuntimeException('OWASYS_SOURCE_HTTP_LOGIN_FAILED');
    }
    $cookie = (string) ($login['cookie'] ?? '');
    if ($cookie === '') {
        throw new RuntimeException('OWASYS_SOURCE_HTTP_LOGIN_COOKIE_MISSING');
    }

    $select = owasys_source_http_request(
        $baseUrl,
        'POST',
        '/applications',
        http_build_query(['owasys_action' => 'select-app', 'owasys_app_id' => 'demo-app']),
        $cookie
    );
    if (($select['status'] ?? 0) !== 303 || ($select['location'] ?? '') !== '/structure') {
        throw new RuntimeException('OWASYS_SOURCE_HTTP_SELECT_APP_FAILED');
    }
    $cookie = (string) ($select['cookie'] ?? $cookie);

    $list = owasys_source_http_json_request($baseUrl, '/source-action.php', ['action' => 'list'], $cookie);
    $listPayload = json_decode((string) ($list['body'] ?? ''), true);
    if (($list['status'] ?? 0) !== 200 || !is_array($listPayload) || ($listPayload['contract'] ?? '') !== 'OWASYS_SOURCE_SCREEN_V1') {
        throw new RuntimeException('OWASYS_SOURCE_HTTP_LIST_INVALID');
    }
    if (!is_array($listPayload['files'] ?? null) || !is_array($listPayload['repository'] ?? null)) {
        throw new RuntimeException('OWASYS_SOURCE_HTTP_LIST_PAYLOAD_INCOMPLETE');
    }

    $path = 'config/site.json';
    $read = owasys_source_http_json_request($baseUrl, '/source-action.php', ['action' => 'read', 'path' => $path], $cookie);
    $readPayload = json_decode((string) ($read['body'] ?? ''), true);
    if (($read['status'] ?? 0) !== 200 || !is_array($readPayload) || ($readPayload['path'] ?? '') !== $path || !is_string($readPayload['content'] ?? null)) {
        throw new RuntimeException('OWASYS_SOURCE_HTTP_READ_INVALID');
    }

    $preview = owasys_source_http_json_request(
        $baseUrl,
        '/source-action.php',
        ['action' => 'preview', 'path' => $path, 'content' => (string) $readPayload['content']],
        $cookie
    );
    $previewPayload = json_decode((string) ($preview['body'] ?? ''), true);
    if (($preview['status'] ?? 0) !== 200 || !is_array($previewPayload) || ($previewPayload['mode'] ?? '') !== 'preview' || ($previewPayload['disk_mutation'] ?? null) !== false) {
        throw new RuntimeException('OWASYS_SOURCE_HTTP_PREVIEW_INVALID');
    }

    $diff = owasys_source_http_json_request($baseUrl, '/source-action.php', ['action' => 'git-diff', 'path' => $path], $cookie);
    $diffPayload = json_decode((string) ($diff['body'] ?? ''), true);
    if (($diff['status'] ?? 0) !== 200 || !is_array($diffPayload) || ($diffPayload['contract'] ?? '') !== 'OWASYS_REPOSITORY_DIFF_V1') {
        throw new RuntimeException('OWASYS_SOURCE_HTTP_GIT_DIFF_INVALID');
    }

    echo "OWASYS_SOURCE_HTTP_SMOKE_OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    if ($hadRuntimeStore && is_file($backup)) {
        @copy($backup, $runtimeStore);
    } elseif (!$hadRuntimeStore) {
        @unlink($runtimeStore);
    }
    owasys_source_http_remove_database($registryDatabase);
    owasys_source_http_remove_tree($tmpRoot);
}
