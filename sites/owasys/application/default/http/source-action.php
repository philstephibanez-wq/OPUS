<?php
declare(strict_types=1);

use Opus\Owasys\ApplicationFileEditor;
use Opus\Owasys\RepositoryInspector;
use Opus\Owasys\RepositoryOperator;

$siteRoot = dirname(__DIR__, 3);
$opusRoot = dirname(dirname($siteRoot));
$autoload = $opusRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo json_encode(['error' => 'OWASYS_COMPOSER_AUTOLOAD_MISSING']);
    exit;
}
require_once $autoload;

$config = json_decode((string) file_get_contents($siteRoot . '/config/site.json'), true);
$auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
$sessionName = (string) ($auth['session_name'] ?? 'OWASYS_LOCAL_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    session_name($sessionName);
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
if (!is_array($_SESSION['owasys_user'] ?? null)) {
    http_response_code(401);
    echo json_encode(['error' => 'OWASYS_SOURCE_AUTH_REQUIRED']);
    exit;
}
$currentApp = is_array($_SESSION['owasys_current_app'] ?? null) ? $_SESSION['owasys_current_app'] : null;
$applicationRoot = is_array($currentApp) ? (string) ($currentApp['root_path'] ?? '') : '';
if ($applicationRoot === '') {
    http_response_code(409);
    echo json_encode(['error' => 'OWASYS_SOURCE_CURRENT_APPLICATION_REQUIRED']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'OWASYS_SOURCE_METHOD_NOT_ALLOWED']);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'OWASYS_SOURCE_PAYLOAD_INVALID']);
    exit;
}

$action = (string) ($payload['action'] ?? '');
$path = (string) ($payload['path'] ?? '');
$editor = new ApplicationFileEditor($opusRoot);
$git = new RepositoryInspector($opusRoot);
$gitOperator = new RepositoryOperator($opusRoot);

try {
    $result = match ($action) {
        'list' => [
            'contract' => 'OWASYS_SOURCE_SCREEN_V1',
            'application_root' => $applicationRoot,
            'files' => $editor->listFiles($applicationRoot),
            'repository' => $git->inspect($applicationRoot),
        ],
        'read' => $editor->read($applicationRoot, $path),
        'preview' => $editor->preview($applicationRoot, $path, (string) ($payload['content'] ?? '')),
        'write' => $editor->write(
            $applicationRoot,
            $path,
            (string) ($payload['content'] ?? ''),
            (string) ($payload['expected_sha256'] ?? '')
        ),
        'git-diff' => [
            'contract' => 'OWASYS_REPOSITORY_DIFF_V1',
            'path' => $path !== '' ? $path : null,
            'diff' => $git->diff($applicationRoot, $path !== '' ? $path : null),
        ],
        'git-stage-application' => $gitOperator->stageApplication($applicationRoot),
        'git-commit-application' => $gitOperator->commitApplication(
            $applicationRoot,
            (string) ($payload['message'] ?? '')
        ),
        default => throw new RuntimeException('OWASYS_SOURCE_ACTION_INVALID'),
    };
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode([
        'error' => $exception->getMessage(),
        'contract' => 'OWASYS_SOURCE_ERROR_V1',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
