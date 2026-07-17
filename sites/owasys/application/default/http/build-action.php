<?php
declare(strict_types=1);

use Opus\Owasys\BuildPipeline;

$siteRoot = dirname(__DIR__, 3);
$opusRoot = dirname(dirname($siteRoot));
$autoload = $opusRoot . '/vendor/autoload.php';
$siteConfigFile = $siteRoot . '/config/site.json';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$fail = static function (int $status, string $error): never {
    http_response_code($status);
    echo json_encode([
        'contract' => 'OWASYS_BUILD_HTTP_RESULT_V1',
        'status' => 'error',
        'error' => $error,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    exit;
};

if (!is_file($autoload)) {
    $fail(500, 'OWASYS_COMPOSER_AUTOLOAD_MISSING');
}
require_once $autoload;

$siteConfig = is_file($siteConfigFile) ? json_decode((string) file_get_contents($siteConfigFile), true) : null;
if (!is_array($siteConfig)) {
    $fail(500, 'OWASYS_SITE_CONFIG_INVALID');
}

$authConfig = is_array($siteConfig['auth'] ?? null) ? $siteConfig['auth'] : [];
$sessionName = (string) ($authConfig['session_name'] ?? 'OWASYS_LOCAL_SESSION');
if (preg_match('/^[A-Za-z0-9_-]+$/', $sessionName) !== 1) {
    $fail(500, 'OWASYS_SESSION_NAME_INVALID');
}
if (session_status() === PHP_SESSION_NONE) {
    session_name($sessionName);
    session_start();
}

if (!is_array($_SESSION['owasys_user'] ?? null)) {
    $fail(401, 'OWASYS_AUTHENTICATION_REQUIRED');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $fail(405, 'OWASYS_BUILD_METHOD_NOT_ALLOWED');
}
if ((string) ($_SERVER['HTTP_X_OWASYS_BUILD'] ?? '') !== 'OWASYS_BUILD_PIPELINE') {
    $fail(403, 'OWASYS_BUILD_HEADER_REQUIRED');
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $fail(400, 'OWASYS_BUILD_PAYLOAD_INVALID');
}
$request = $payload['request'] ?? null;
if (!is_array($request)) {
    $fail(400, 'OWASYS_BUILD_REQUEST_INVALID');
}
$mode = (string) ($payload['mode'] ?? 'preview');
$outputZip = isset($payload['output_zip']) && is_string($payload['output_zip']) && trim($payload['output_zip']) !== ''
    ? trim($payload['output_zip'])
    : null;
$overwrite = ($payload['overwrite'] ?? false) === true;

try {
    $result = (new BuildPipeline($opusRoot))->run($request, $mode, $outputZip, $overwrite);
    echo json_encode([
        'contract' => 'OWASYS_BUILD_HTTP_RESULT_V1',
        'status' => 'ok',
        'result' => $result,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $exception) {
    $fail(422, $exception->getMessage());
}
