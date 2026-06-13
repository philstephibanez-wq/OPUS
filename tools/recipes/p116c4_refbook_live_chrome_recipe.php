<?php

declare(strict_types=1);

/**
 * P116C4 RefBook live Chrome recipe.
 *
 * Contract:
 *   - uses the local Chrome extension as the browser-side runtime robot;
 *   - launches Chrome with the extension loaded and DevTools remote debugging;
 *   - inspects the real OPUS_REF_BOOK DOM through Chrome, not through PHP string
 *     rendering only;
 *   - fails if the extension marker is not injected, if the real layout slots are
 *     missing, or if a runtime error marker appears in the visible page.
 */
$root = dirname(__DIR__, 2);
$runtime = $root . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'recipes' . DIRECTORY_SEPARATOR . 'p116c4_refbook_live_chrome_' . date('Ymd_His');
ensureDirectory($runtime);

$baseUrl = rtrim((string)(getenv('OPUS_RECIPE_REFBOOK_BASE_URL') ?: 'http://127.0.0.1/OPUS_REF_BOOK'), '/');
$url = (string)(getenv('OPUS_P116C4_REFBOOK_URL') ?: $baseUrl . '/?lang=fr&theme=night');
$extensionPath = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'chrome_extension' . DIRECTORY_SEPARATOR . 'opus_runtime_robot';
$chromePath = findChromePath();
$port = (int)((string)(getenv('OPUS_P116C4_CHROME_DEBUG_PORT') ?: '9224'));
$profile = $runtime . DIRECTORY_SEPARATOR . 'chrome-profile';
ensureDirectory($profile);

$checks = [];
check($checks, is_file($chromePath), 'P116C4_CHROME_BINARY_OK', 'P116C4_CHROME_BINARY_MISSING', $chromePath);
check($checks, is_dir($extensionPath), 'P116C4_CHROME_EXTENSION_DIR_OK', 'P116C4_CHROME_EXTENSION_DIR_MISSING', $extensionPath);
validateExtension($checks, $extensionPath);

$process = null;
try {
    if (failed($checks)) {
        finish($runtime, $checks, ['url' => $url, 'chrome' => $chromePath, 'extension' => $extensionPath]);
    }

    $process = launchChrome($chromePath, $port, $profile, $extensionPath, $url, $runtime);
    $target = waitForTarget($port, $url, 15.0);
    check($checks, is_array($target), 'P116C4_CHROME_TARGET_OK', 'P116C4_CHROME_TARGET_MISSING', $url);
    if (!is_array($target)) {
        finish($runtime, $checks, ['url' => $url, 'chrome' => $chromePath, 'extension' => $extensionPath]);
    }

    $inspection = evaluatePageInspection((string)$target['webSocketDebuggerUrl']);
    writeJson($runtime . DIRECTORY_SEPARATOR . 'dom-inspection.json', $inspection);

    check($checks, ($inspection['extensionInstalled'] ?? false) === true, 'P116C4_CHROME_EXTENSION_INJECTED_OK', 'P116C4_CHROME_EXTENSION_NOT_INJECTED');
    check($checks, ($inspection['runtimeError'] ?? true) === false, 'P116C4_CHROME_NO_RUNTIME_ERROR_OK', 'P116C4_CHROME_RUNTIME_ERROR_VISIBLE', (string)($inspection['bodyExcerpt'] ?? ''));
    check($checks, ($inspection['hasHeader'] ?? false) === true, 'P116C4_CHROME_HEADER_OK', 'P116C4_CHROME_HEADER_MISSING');
    check($checks, ($inspection['hasSidebar'] ?? false) === true, 'P116C4_CHROME_SIDEBAR_OK', 'P116C4_CHROME_SIDEBAR_MISSING');
    check($checks, ($inspection['hasMain'] ?? false) === true, 'P116C4_CHROME_MAIN_OK', 'P116C4_CHROME_MAIN_MISSING');
    check($checks, ($inspection['hasFooter'] ?? false) === true, 'P116C4_CHROME_FOOTER_OK', 'P116C4_CHROME_FOOTER_MISSING');
    check($checks, str_contains((string)($inspection['url'] ?? ''), 'OPUS_REF_BOOK'), 'P116C4_CHROME_REFBOOK_URL_OK', 'P116C4_CHROME_REFBOOK_URL_INVALID', (string)($inspection['url'] ?? ''));
} finally {
    if (is_resource($process)) {
        @proc_terminate($process);
        @proc_close($process);
    }
}

finish($runtime, $checks, ['url' => $url, 'chrome' => $chromePath, 'extension' => $extensionPath]);

function validateExtension(array &$checks, string $extensionPath): void
{
    foreach (['manifest.json', 'content.js', 'popup.html', 'popup.css', 'popup.js'] as $file) {
        check($checks, is_file($extensionPath . DIRECTORY_SEPARATOR . $file), 'P116C4_EXTENSION_FILE_OK=' . $file, 'P116C4_EXTENSION_FILE_MISSING', $file);
    }
    $manifest = json_decode(readText($extensionPath . DIRECTORY_SEPARATOR . 'manifest.json'), true);
    check($checks, is_array($manifest) && ($manifest['manifest_version'] ?? null) === 3, 'P116C4_EXTENSION_MANIFEST_V3_OK', 'P116C4_EXTENSION_MANIFEST_V3_MISSING');
    $serialized = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    check($checks, !str_contains($serialized, '<all_urls>') && !str_contains($serialized, 'https://*/*'), 'P116C4_EXTENSION_LOCAL_ONLY_OK', 'P116C4_EXTENSION_BROAD_HOST_FORBIDDEN');
    $content = readText($extensionPath . DIRECTORY_SEPARATOR . 'content.js');
    check($checks, str_contains($content, 'OPUS_RUNTIME_ROBOT_INSPECT'), 'P116C4_EXTENSION_INSPECT_MESSAGE_OK', 'P116C4_EXTENSION_INSPECT_MESSAGE_MISSING');
    check($checks, str_contains($content, 'data-opus-runtime-robot-extension'), 'P116C4_EXTENSION_DOM_MARKER_OK', 'P116C4_EXTENSION_DOM_MARKER_MISSING');
}

function findChromePath(): string
{
    $configured = trim((string)(getenv('OPUS_CHROME_PATH') ?: ''));
    if ($configured !== '') { return $configured; }
    $candidates = [];
    foreach (['ProgramFiles', 'ProgramFiles(x86)', 'LOCALAPPDATA'] as $env) {
        $base = getenv($env);
        if (is_string($base) && $base !== '') {
            $candidates[] = $base . DIRECTORY_SEPARATOR . 'Google' . DIRECTORY_SEPARATOR . 'Chrome' . DIRECTORY_SEPARATOR . 'Application' . DIRECTORY_SEPARATOR . 'chrome.exe';
        }
    }
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) { return $candidate; }
    }
    return $candidates[0] ?? 'chrome.exe';
}

function launchChrome(string $chrome, int $port, string $profile, string $extensionPath, string $url, string $runtime)
{
    $cmd = quote($chrome)
        . ' --remote-debugging-port=' . $port
        . ' --user-data-dir=' . quote($profile)
        . ' --load-extension=' . quote($extensionPath)
        . ' --disable-extensions-except=' . quote($extensionPath)
        . ' --no-first-run --no-default-browser-check '
        . quote($url);
    writeText($runtime . DIRECTORY_SEPARATOR . 'chrome-command.txt', $cmd . PHP_EOL);
    $descriptor = [0 => ['pipe', 'r'], 1 => ['file', $runtime . DIRECTORY_SEPARATOR . 'chrome.stdout.log', 'a'], 2 => ['file', $runtime . DIRECTORY_SEPARATOR . 'chrome.stderr.log', 'a']];
    $process = proc_open($cmd, $descriptor, $pipes);
    if (!is_resource($process)) {
        fail('P116C4_CHROME_LAUNCH_FAILED', $cmd);
    }
    fclose($pipes[0]);
    return $process;
}

function waitForTarget(int $port, string $url, float $seconds): ?array
{
    $deadline = microtime(true) + $seconds;
    do {
        $targets = httpJson('http://127.0.0.1:' . $port . '/json/list');
        foreach ($targets as $target) {
            if (!is_array($target)) { continue; }
            $targetUrl = (string)($target['url'] ?? '');
            if (($target['type'] ?? '') === 'page' && str_contains($targetUrl, parse_url($url, PHP_URL_PATH) ?: 'OPUS_REF_BOOK') && isset($target['webSocketDebuggerUrl'])) {
                return $target;
            }
        }
        usleep(300000);
    } while (microtime(true) < $deadline);
    return null;
}

function evaluatePageInspection(string $webSocketUrl): array
{
    $socket = wsConnect($webSocketUrl);
    $expression = <<<'JS'
(function () {
  const text = document.body ? (document.body.innerText || '') : '';
  const q = (s) => document.querySelector(s) !== null;
  return {
    url: window.location.href,
    title: document.title || '',
    lang: document.documentElement ? (document.documentElement.getAttribute('lang') || '') : '',
    extensionInstalled: document.documentElement ? document.documentElement.getAttribute('data-opus-runtime-robot-extension') === 'installed' : false,
    runtimeError: /OPUS_[A-Z0-9_]*ERROR|Fatal error|Parse error|Warning:/i.test(text),
    hasHeader: q('header,[role="banner"],.refbook-header'),
    hasSidebar: q('aside,[role="navigation"],.refbook-sidebar,.sidebar'),
    hasMain: q('main,[role="main"],.refbook-main'),
    hasFooter: q('footer,[role="contentinfo"],.refbook-footer'),
    diagramCount: document.querySelectorAll('svg,.diagram,[data-diagram],.mermaid').length,
    bodyExcerpt: text.slice(0, 1000)
  };
}())
JS;
    wsSend($socket, json_encode(['id' => 1, 'method' => 'Runtime.evaluate', 'params' => ['expression' => $expression, 'returnByValue' => true]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
    $deadline = microtime(true) + 8.0;
    do {
        $payload = wsRead($socket);
        $decoded = json_decode($payload, true);
        if (is_array($decoded) && ($decoded['id'] ?? null) === 1) {
            fclose($socket);
            $value = $decoded['result']['result']['value'] ?? null;
            return is_array($value) ? $value : ['runtimeError' => true, 'bodyExcerpt' => 'P116C4_CDP_VALUE_INVALID'];
        }
    } while (microtime(true) < $deadline);
    fclose($socket);
    return ['runtimeError' => true, 'bodyExcerpt' => 'P116C4_CDP_EVALUATE_TIMEOUT'];
}

function wsConnect(string $url)
{
    $parts = parse_url($url);
    $host = (string)($parts['host'] ?? '127.0.0.1');
    $port = (int)($parts['port'] ?? 80);
    $path = (string)($parts['path'] ?? '/');
    $socket = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, 5.0);
    if (!is_resource($socket)) { fail('P116C4_CDP_SOCKET_FAILED', $errstr); }
    stream_set_timeout($socket, 5);
    $key = base64_encode(random_bytes(16));
    $headers = "GET " . $path . " HTTP/1.1\r\nHost: " . $host . ":" . $port . "\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: " . $key . "\r\nSec-WebSocket-Version: 13\r\n\r\n";
    fwrite($socket, $headers);
    $response = '';
    while (!str_contains($response, "\r\n\r\n")) {
        $chunk = fread($socket, 1024);
        if (!is_string($chunk) || $chunk === '') { break; }
        $response .= $chunk;
    }
    if (!str_contains($response, ' 101 ')) { fail('P116C4_CDP_WEBSOCKET_HANDSHAKE_FAILED', $response); }
    return $socket;
}

function wsSend($socket, string $payload): void
{
    $length = strlen($payload);
    $header = chr(0x81);
    if ($length <= 125) { $header .= chr(0x80 | $length); }
    elseif ($length <= 65535) { $header .= chr(0x80 | 126) . pack('n', $length); }
    else { fail('P116C4_CDP_FRAME_TOO_LARGE', (string)$length); }
    $mask = random_bytes(4);
    $masked = '';
    for ($i = 0; $i < $length; $i++) { $masked .= $payload[$i] ^ $mask[$i % 4]; }
    fwrite($socket, $header . $mask . $masked);
}

function wsRead($socket): string
{
    $h = readBytes($socket, 2);
    $b1 = ord($h[0]);
    $b2 = ord($h[1]);
    $opcode = $b1 & 0x0f;
    $masked = ($b2 & 0x80) !== 0;
    $length = $b2 & 0x7f;
    if ($length === 126) { $length = unpack('n', readBytes($socket, 2))[1]; }
    elseif ($length === 127) { fail('P116C4_CDP_FRAME_64BIT_UNSUPPORTED'); }
    $mask = $masked ? readBytes($socket, 4) : '';
    $payload = $length > 0 ? readBytes($socket, $length) : '';
    if ($masked) { $out = ''; for ($i = 0; $i < $length; $i++) { $out .= $payload[$i] ^ $mask[$i % 4]; } $payload = $out; }
    if ($opcode === 8) { fail('P116C4_CDP_WEBSOCKET_CLOSED', $payload); }
    return $payload;
}

function readBytes($socket, int $length): string
{
    $data = '';
    while (strlen($data) < $length) {
        $chunk = fread($socket, $length - strlen($data));
        if (!is_string($chunk) || $chunk === '') { fail('P116C4_CDP_READ_FAILED'); }
        $data .= $chunk;
    }
    return $data;
}

function httpJson(string $url): array
{
    $body = @file_get_contents($url);
    if (!is_string($body) || $body === '') { return []; }
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : [];
}

function finish(string $runtime, array $checks, array $summary): never
{
    $failed = failed($checks);
    $report = ['recipe' => 'P116C4_REFBOOK_LIVE_CHROME_RECIPE', 'status' => $failed ? 'FAILED' : 'OK', 'summary' => $summary, 'checks' => $checks];
    writeJson($runtime . DIRECTORY_SEPARATOR . 'report.json', $report);
    echo 'P116C4_REFBOOK_LIVE_CHROME_RECIPE_REPORT=' . $runtime . DIRECTORY_SEPARATOR . 'report.json' . PHP_EOL;
    foreach ($checks as $check) { echo '[' . $check['status'] . '] ' . $check['code'] . ($check['detail'] !== '' ? ' :: ' . $check['detail'] : '') . PHP_EOL; }
    if ($failed) { fwrite(STDERR, 'P116C4_REFBOOK_LIVE_CHROME_RECIPE_FAILED' . PHP_EOL); exit(1); }
    echo 'P116C4_REFBOOK_LIVE_CHROME_RECIPE_OK' . PHP_EOL;
    exit(0);
}

function failed(array $checks): bool { foreach ($checks as $check) { if (($check['status'] ?? '') === 'FAILED') { return true; } } return false; }
function check(array &$checks, bool $condition, string $ok, string $fail, string $detail = ''): void { $checks[] = ['status' => $condition ? 'OK' : 'FAILED', 'code' => $condition ? $ok : $fail, 'detail' => $detail]; }
function ensureDirectory(string $path): void { if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) { fail('P116C4_DIRECTORY_CREATE_FAILED', $path); } }
function readText(string $path): string { $text = @file_get_contents($path); return is_string($text) ? $text : ''; }
function writeText(string $path, string $content): void { if (file_put_contents($path, $content) === false) { fail('P116C4_WRITE_FAILED', $path); } }
function writeJson(string $path, array $payload): void { $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); writeText($path, (is_string($json) ? $json : '{}') . PHP_EOL); }
function quote(string $value): string { return '"' . str_replace('"', '\\"', $value) . '"'; }
function fail(string $code, string $detail = ''): never { fwrite(STDERR, $code . ($detail !== '' ? '=' . $detail : '') . PHP_EOL); exit(1); }
