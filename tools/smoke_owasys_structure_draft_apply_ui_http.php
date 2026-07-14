<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';
$publicRoot = $siteRoot . DIRECTORY_SEPARATOR . 'www';
$runtimeStore = $siteRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'local-users.json';
$registryDatabaseRelative = 'var/registry/owasys-structure-apply-ui-http-smoke.sqlite';
$registrySeedRelative = 'var/registry/owasys-structure-apply-ui-http-smoke.seed.json';
$registryDatabase = $siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $registryDatabaseRelative);
$registrySeed = $siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $registrySeedRelative);
$tmpRoot = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'owasys-structure-apply-ui-http-smoke';
$router = $tmpRoot . DIRECTORY_SEPARATOR . 'router.php';
$backup = $tmpRoot . DIRECTORY_SEPARATOR . 'local-users.backup.json';
$sourceSiteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'demo-app';
$smokeSiteId = 'owasys-apply-ui-http-smoke-demo';
$smokeSiteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . $smokeSiteId;
$demoForbiddenStateRoot = $sourceSiteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'states' . DIRECTORY_SEPARATOR . 'uiapply';
$smokeStateRoot = $smokeSiteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'states' . DIRECTORY_SEPARATOR . 'uiapply';

if (!function_exists('proc_open')) {
    fwrite(STDERR, "OWASYS_STRUCTURE_APPLY_UI_HTTP_PROC_OPEN_UNAVAILABLE\n");
    exit(1);
}
if (!class_exists(SQLite3::class)) {
    fwrite(STDERR, "OWASYS_STRUCTURE_APPLY_UI_HTTP_SQLITE3_EXTENSION_MISSING\n");
    exit(1);
}

function owasys_structure_apply_ui_http_remove_tree(string $path): void
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

function owasys_structure_apply_ui_http_copy_tree(string $source, string $target): void
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $destination = $target . DIRECTORY_SEPARATOR . $relative;
        if ($item->isDir()) {
            if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
                throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_COPY_DIR_FAILED: ' . $destination);
            }
        } else {
            $parent = dirname($destination);
            if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
                throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_COPY_PARENT_FAILED: ' . $parent);
            }
            if (!copy($item->getPathname(), $destination)) {
                throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_COPY_FILE_FAILED: ' . $destination);
            }
        }
    }
}

function owasys_structure_apply_ui_http_remove_registry_artifacts(string $databasePath, string $seedPath): void
{
    @unlink($databasePath);
    @unlink($databasePath . '-shm');
    @unlink($databasePath . '-wal');
    @unlink($seedPath);
    $registryDir = dirname($databasePath);
    if (is_dir($registryDir) && count(scandir($registryDir) ?: []) === 2) {
        @rmdir($registryDir);
    }
}

function owasys_structure_apply_ui_http_status(array $headers): int
{
    foreach ($headers as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $match) === 1) {
            return (int) $match[1];
        }
    }
    return 0;
}

function owasys_structure_apply_ui_http_header(array $headers, string $name): ?string
{
    $prefix = strtolower($name) . ':';
    foreach ($headers as $header) {
        if (str_starts_with(strtolower($header), $prefix)) {
            return trim(substr($header, strlen($prefix)));
        }
    }
    return null;
}

function owasys_structure_apply_ui_http_cookie(array $headers, string $existingCookie): string
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

function owasys_structure_apply_ui_http_request(string $baseUrl, string $method, string $path, string $body = '', string $cookie = ''): array
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
        'status' => owasys_structure_apply_ui_http_status($headersOut),
        'location' => owasys_structure_apply_ui_http_header($headersOut, 'Location'),
        'cookie' => owasys_structure_apply_ui_http_cookie($headersOut, $cookie),
        'body' => is_string($responseBody) ? $responseBody : '',
    ];
}

function owasys_structure_apply_ui_http_sqlite_value(string $databasePath, string $sql): mixed
{
    $db = new SQLite3($databasePath);
    try {
        return $db->querySingle($sql);
    } finally {
        $db->close();
    }
}

function owasys_structure_apply_ui_http_assert_redirect(array $response, string $expectedLocation, string $error): void
{
    if (($response['status'] ?? 0) !== 303 || ($response['location'] ?? '') !== $expectedLocation) {
        fwrite(STDERR, $error . ': status=' . (string) ($response['status'] ?? 0) . ' location=' . (string) ($response['location'] ?? '') . "\n");
        exit(1);
    }
}

function owasys_structure_apply_ui_http_stop_server(mixed $server): void
{
    if (!is_resource($server)) {
        return;
    }
    $status = proc_get_status($server);
    $pid = is_array($status) ? (int) ($status['pid'] ?? 0) : 0;
    @proc_terminate($server);
    if ($pid > 0) {
        if (DIRECTORY_SEPARATOR === '\\') {
            @exec('taskkill /F /T /PID ' . $pid . ' 2>NUL');
        } else {
            @exec('kill -TERM ' . $pid . ' 2>/dev/null');
            usleep(100000);
            @exec('kill -KILL ' . $pid . ' 2>/dev/null');
        }
    }
    @proc_close($server);
}

foreach ([$publicRoot, $sourceSiteRoot] as $requiredDir) {
    if (!is_dir($requiredDir)) {
        fwrite(STDERR, "OWASYS_STRUCTURE_APPLY_UI_HTTP_REQUIRED_DIR_MISSING: {$requiredDir}\n");
        exit(1);
    }
}
foreach ([__FILE__, $publicRoot . DIRECTORY_SEPARATOR . 'index.php'] as $lintFile) {
    $output = [];
    $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($lintFile) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, "OWASYS_STRUCTURE_APPLY_UI_HTTP_PARSE_ERROR: {$lintFile}\n" . implode("\n", $output) . "\n");
        exit(1);
    }
}

owasys_structure_apply_ui_http_remove_tree($tmpRoot);
owasys_structure_apply_ui_http_remove_tree($smokeSiteRoot);
owasys_structure_apply_ui_http_remove_registry_artifacts($registryDatabase, $registrySeed);
if (is_dir($demoForbiddenStateRoot)) {
    fwrite(STDERR, "OWASYS_STRUCTURE_APPLY_UI_HTTP_DEMO_APP_PREEXISTING_STATE\n");
    exit(1);
}
if (!is_dir($tmpRoot) && !mkdir($tmpRoot, 0775, true) && !is_dir($tmpRoot)) {
    fwrite(STDERR, "OWASYS_STRUCTURE_APPLY_UI_HTTP_TMP_CREATE_FAILED\n");
    exit(1);
}

$hadRuntimeStore = is_file($runtimeStore);
if ($hadRuntimeStore && !copy($runtimeStore, $backup)) {
    fwrite(STDERR, "OWASYS_STRUCTURE_APPLY_UI_HTTP_BACKUP_FAILED\n");
    exit(1);
}

$server = null;
$port = 20180 + random_int(0, 900);
$baseUrl = 'http://127.0.0.1:' . $port;

try {
    owasys_structure_apply_ui_http_copy_tree($sourceSiteRoot, $smokeSiteRoot);
    $siteFile = $smokeSiteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'site.json';
    $fsmFile = $smokeSiteRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'application.fsm.json';
    $site = json_decode((string) file_get_contents($siteFile), true);
    $fsm = json_decode((string) file_get_contents($fsmFile), true);
    if (!is_array($site) || !is_array($fsm)) {
        throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_TEMP_SITE_CONFIG_INVALID');
    }
    $site['site_id'] = $smokeSiteId;
    $site['site_name'] = 'OWASYS Apply UI HTTP Smoke Demo';
    $fsm['site_id'] = $smokeSiteId;
    foreach ([[$siteFile, $site], [$fsmFile, $fsm]] as [$file, $json]) {
        $encoded = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || file_put_contents($file, $encoded . "\n") === false) {
            throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_TEMP_SITE_WRITE_FAILED');
        }
    }

    $registryDir = dirname($registrySeed);
    if (!is_dir($registryDir) && !mkdir($registryDir, 0775, true) && !is_dir($registryDir)) {
        throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_REGISTRY_DIR_CREATE_FAILED');
    }
    $seed = [
        'contract' => 'OWASYS_REGISTRY_SEED_V1',
        'applications' => [
            [
                'id' => 'owasys',
                'slug' => 'owasys',
                'name' => 'OPUS OWASYS',
                'kind' => 'fullstack',
                'root_path' => 'sites/owasys',
                'public_root' => 'www',
                'default_locale' => 'fr',
                'theme' => 'owasys',
                'status' => 'validated',
            ],
            [
                'id' => $smokeSiteId,
                'slug' => $smokeSiteId,
                'name' => 'OWASYS Apply UI HTTP Smoke Demo',
                'kind' => 'fullstack',
                'root_path' => 'sites/' . $smokeSiteId,
                'public_root' => 'www',
                'default_locale' => 'fr',
                'theme' => 'starter',
                'status' => 'validated',
                'blueprint' => 'opus-site-standard',
                'generated_by' => 'owasys',
            ],
        ],
    ];
    $encodedSeed = json_encode($seed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($encodedSeed) || file_put_contents($registrySeed, $encodedSeed . "\n") === false) {
        throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_SEED_WRITE_FAILED');
    }

    $runtimeStoreDir = dirname($runtimeStore);
    if (!is_dir($runtimeStoreDir) && !mkdir($runtimeStoreDir, 0775, true) && !is_dir($runtimeStoreDir)) {
        throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_AUTH_DIR_CREATE_FAILED');
    }
    $password = 'OwasysApplyUiHttpPass123!';
    $store = ['contract' => 'OWASYS_LOCAL_USER_STORE_V1', 'committed' => false, 'users' => ['apply-ui-http' => ['id' => 'apply-ui-http', 'label' => 'Apply UI HTTP Smoke', 'profile' => 'dev', 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'must_change_password' => false]]];
    $encodedStore = json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($encodedStore) || file_put_contents($runtimeStore, $encodedStore . "\n") === false) {
        throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_AUTH_STORE_WRITE_FAILED');
    }

    $publicRootExport = var_export(str_replace('\\', '/', $publicRoot), true);
    $registryDatabaseExport = var_export($registryDatabaseRelative, true);
    $registrySeedExport = var_export($registrySeedRelative, true);
    $routerSource = <<<'PHP'
<?php
declare(strict_types=1);

$publicRoot = __PUBLIC_ROOT__;
$owasysRegistryDatabaseRelative = __REGISTRY_DATABASE__;
$owasysRegistrySeedRelative = __REGISTRY_SEED__;
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
    $routerSource = str_replace(['__PUBLIC_ROOT__', '__REGISTRY_DATABASE__', '__REGISTRY_SEED__'], [$publicRootExport, $registryDatabaseExport, $registrySeedExport], $routerSource);
    if (file_put_contents($router, $routerSource) === false) {
        throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_ROUTER_WRITE_FAILED');
    }

    $nullDevice = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
    $command = PHP_BINARY . ' -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($publicRoot) . ' ' . escapeshellarg($router);
    $server = proc_open($command, [0 => ['file', $nullDevice, 'r'], 1 => ['file', $nullDevice, 'w'], 2 => ['file', $nullDevice, 'w']], $pipes, $root);
    if (!is_resource($server)) {
        throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_SERVER_START_FAILED');
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
        throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_SERVER_NOT_READY');
    }

    $login = owasys_structure_apply_ui_http_request($baseUrl, 'POST', '/login', http_build_query(['owasys_action' => 'password-signin', 'owasys_username' => 'apply-ui-http', 'owasys_password' => $password]));
    owasys_structure_apply_ui_http_assert_redirect($login, '/', 'OWASYS_STRUCTURE_APPLY_UI_HTTP_LOGIN_REDIRECT_INVALID');
    $cookie = (string) ($login['cookie'] ?? '');
    if ($cookie === '') {
        throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_LOGIN_COOKIE_MISSING');
    }

    $registry = owasys_structure_apply_ui_http_request($baseUrl, 'GET', '/applications', '', $cookie);
    if (($registry['status'] ?? 0) !== 200 || !str_contains((string) ($registry['body'] ?? ''), $smokeSiteId)) {
        throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_REGISTRY_RENDER_INVALID');
    }
    $cookie = (string) ($registry['cookie'] ?? $cookie);

    $select = owasys_structure_apply_ui_http_request($baseUrl, 'POST', '/applications', http_build_query(['owasys_action' => 'select-app', 'owasys_app_id' => $smokeSiteId]), $cookie);
    owasys_structure_apply_ui_http_assert_redirect($select, '/structure', 'OWASYS_STRUCTURE_APPLY_UI_HTTP_SELECT_REDIRECT_INVALID');
    $cookie = (string) ($select['cookie'] ?? $cookie);

    $structure = owasys_structure_apply_ui_http_request($baseUrl, 'GET', '/structure', '', $cookie);
    if (($structure['status'] ?? 0) !== 200 || !str_contains((string) ($structure['body'] ?? ''), 'OWASYS_STRUCTURE_ADD_STATE_DRAFT_FORM')) {
        throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_STRUCTURE_RENDER_INVALID');
    }

    $draft = owasys_structure_apply_ui_http_request($baseUrl, 'POST', '/structure', http_build_query(['owasys_action' => 'prepare-add-state-draft', 'owasys_state_id' => 'uiapply', 'owasys_route_path' => '/uiapply', 'owasys_title_key' => 'state.uiapply.title', 'owasys_event_name' => 'open_uiapply']), $cookie);
    if (($draft['status'] ?? 0) !== 200 || !str_contains((string) ($draft['body'] ?? ''), 'OWASYS_STRUCTURE_DRAFT_RESULT') || !str_contains((string) ($draft['body'] ?? ''), 'OWASYS_STRUCTURE_APPLY_DRAFT_FORM')) {
        throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_DRAFT_RENDER_INVALID');
    }
    if ((int) owasys_structure_apply_ui_http_sqlite_value($registryDatabase, "SELECT COUNT(*) FROM owasys_application_events WHERE event_type = 'draft_add_state'") !== 1) {
        throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_DRAFT_EVENT_NOT_PERSISTED');
    }

    $apply = owasys_structure_apply_ui_http_request($baseUrl, 'POST', '/structure', http_build_query(['owasys_action' => 'apply-structure-draft', 'owasys_draft_id' => 1]), $cookie);
    if (($apply['status'] ?? 0) !== 200 || !str_contains((string) ($apply['body'] ?? ''), 'OWASYS_STRUCTURE_APPLY_RESULT')) {
        throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_APPLY_RENDER_INVALID');
    }
    if (!is_dir($smokeStateRoot) || is_dir($demoForbiddenStateRoot)) {
        throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_APPLY_DISK_TARGET_INVALID');
    }
    if ((int) owasys_structure_apply_ui_http_sqlite_value($registryDatabase, "SELECT COUNT(*) FROM owasys_application_events WHERE event_type = 'apply_structure_draft'") !== 1) {
        throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_APPLY_EVENT_NOT_PERSISTED');
    }
    $applyContext = (string) owasys_structure_apply_ui_http_sqlite_value($registryDatabase, "SELECT value_json FROM owasys_runtime_context WHERE key = 'last_structure_apply'");
    if (!str_contains($applyContext, 'uiapply') || !str_contains($applyContext, 'OWASYS_STRUCTURE_DRAFT_APPLY_RESULT_V1')) {
        throw new RuntimeException('OWASYS_STRUCTURE_APPLY_UI_HTTP_APPLY_CONTEXT_NOT_PERSISTED');
    }

    $output = [];
    $code = 0;
    exec(PHP_BINARY . ' ' . escapeshellarg($root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'opus') . ' validate:site ' . escapeshellarg($smokeSiteId) . ' 2>&1', $output, $code);
    if ($code !== 0 || !in_array('OPUS_VALIDATE_SITE_OK: ' . $smokeSiteId, $output, true)) {
        throw new RuntimeException("OWASYS_STRUCTURE_APPLY_UI_HTTP_VALIDATE_SITE_FAILED\n" . implode("\n", $output));
    }
} finally {
    owasys_structure_apply_ui_http_stop_server($server);
    if ($hadRuntimeStore && is_file($backup)) {
        @copy($backup, $runtimeStore);
    } elseif (!$hadRuntimeStore && is_file($runtimeStore)) {
        @unlink($runtimeStore);
        $authDir = dirname($runtimeStore);
        if (is_dir($authDir) && count(scandir($authDir) ?: []) === 2) {
            @rmdir($authDir);
        }
    }
    owasys_structure_apply_ui_http_remove_tree($smokeSiteRoot);
    owasys_structure_apply_ui_http_remove_registry_artifacts($registryDatabase, $registrySeed);
    owasys_structure_apply_ui_http_remove_tree($tmpRoot);
}

if (is_dir($smokeSiteRoot)) {
    fwrite(STDERR, "OWASYS_STRUCTURE_APPLY_UI_HTTP_SITE_CLEANUP_FAILED\n");
    exit(1);
}
if (is_file($registryDatabase) || is_file($registrySeed)) {
    fwrite(STDERR, "OWASYS_STRUCTURE_APPLY_UI_HTTP_REGISTRY_CLEANUP_FAILED\n");
    exit(1);
}
if (is_dir($demoForbiddenStateRoot)) {
    fwrite(STDERR, "OWASYS_STRUCTURE_APPLY_UI_HTTP_DEMO_APP_MUTATED\n");
    exit(1);
}

echo "OWASYS_STRUCTURE_DRAFT_APPLY_UI_HTTP_SMOKE_OK\n";
