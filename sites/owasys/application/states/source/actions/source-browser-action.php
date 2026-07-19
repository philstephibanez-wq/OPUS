<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (!is_array($_SESSION['owasys_user'] ?? null)) {
    http_response_code(401);
    echo json_encode(['error' => 'OWASYS_SOURCE_AUTH_REQUIRED']);
    return;
}

$currentApp = is_array($_SESSION['owasys_current_app'] ?? null) ? $_SESSION['owasys_current_app'] : null;
$rootPath = is_array($currentApp) ? trim((string) ($currentApp['root_path'] ?? '')) : '';
if ($rootPath === '') {
    http_response_code(409);
    echo json_encode(['error' => 'OWASYS_SOURCE_CURRENT_APPLICATION_REQUIRED']);
    return;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'OWASYS_SOURCE_METHOD_NOT_ALLOWED']);
    return;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'OWASYS_SOURCE_PAYLOAD_INVALID']);
    return;
}

$opusRoot = dirname(dirname(dirname(dirname(dirname(__DIR__)))));
$applicationRoot = realpath($opusRoot . '/' . ltrim(str_replace('\\', '/', $rootPath), '/'));
$opusRootReal = realpath($opusRoot);
if (!is_string($applicationRoot) || !is_string($opusRootReal) || !str_starts_with($applicationRoot, $opusRootReal . DIRECTORY_SEPARATOR) || !is_dir($applicationRoot)) {
    http_response_code(422);
    echo json_encode(['error' => 'OWASYS_SOURCE_APPLICATION_ROOT_INVALID']);
    return;
}

$allowedExtensions = ['php', 'json', 'js', 'mjs', 'cjs', 'css', 'html', 'htm', 'sql', 'md', 'markdown', 'score', 'xml', 'yaml', 'yml', 'txt'];
$blockedNames = ['.env', '.env.local', '.env.production', '.env.development'];
$blockedSegments = ['.git', 'vendor', 'node_modules', 'var', 'cache', 'logs', 'tmp'];

$normalizeRelative = static function (string $path): string {
    $path = trim(str_replace('\\', '/', $path), '/');
    if ($path === '' || str_contains($path, "\0") || str_contains($path, '..')) {
        throw new RuntimeException('OWASYS_SOURCE_PATH_INVALID');
    }
    return $path;
};

$isAllowed = static function (string $relative) use ($allowedExtensions, $blockedNames, $blockedSegments): bool {
    $segments = explode('/', str_replace('\\', '/', $relative));
    foreach ($segments as $segment) {
        if (in_array($segment, $blockedSegments, true) || in_array(strtolower($segment), $blockedNames, true)) {
            return false;
        }
    }
    $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
    return in_array($extension, $allowedExtensions, true);
};

try {
    $action = (string) ($payload['action'] ?? '');
    if ($action === 'list') {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($applicationRoot, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || $file->isLink()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($applicationRoot) + 1));
            if (!$isAllowed($relative)) {
                continue;
            }
            $size = $file->getSize();
            if ($size > 1048576) {
                continue;
            }
            $files[] = ['path' => $relative, 'bytes' => $size];
            if (count($files) >= 5000) {
                break;
            }
        }
        usort($files, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));
        echo json_encode([
            'contract' => 'OWASYS_SOURCE_BROWSER_V1',
            'application_root' => $rootPath,
            'files' => $files,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return;
    }

    if ($action === 'read') {
        $relative = $normalizeRelative((string) ($payload['path'] ?? ''));
        if (!$isAllowed($relative)) {
            throw new RuntimeException('OWASYS_SOURCE_FILE_NOT_ALLOWED');
        }
        $candidate = realpath($applicationRoot . '/' . $relative);
        if (!is_string($candidate) || !str_starts_with($candidate, $applicationRoot . DIRECTORY_SEPARATOR) || !is_file($candidate) || is_link($candidate)) {
            throw new RuntimeException('OWASYS_SOURCE_FILE_INVALID');
        }
        $size = filesize($candidate);
        if (!is_int($size) || $size > 1048576) {
            throw new RuntimeException('OWASYS_SOURCE_FILE_TOO_LARGE');
        }
        $content = file_get_contents($candidate);
        if (!is_string($content)) {
            throw new RuntimeException('OWASYS_SOURCE_FILE_READ_FAILED');
        }
        echo json_encode([
            'contract' => 'OWASYS_SOURCE_FILE_V1',
            'path' => $relative,
            'bytes' => $size,
            'sha256' => hash('sha256', $content),
            'content' => $content,
            'read_only' => true,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return;
    }

    throw new RuntimeException('OWASYS_SOURCE_ACTION_INVALID');
} catch (Throwable $exception) {
    http_response_code(422);
    echo json_encode([
        'contract' => 'OWASYS_SOURCE_ERROR_V1',
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
