<?php

declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (!is_string($path) || $path === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => ['code' => 'OPUS_REFBOOK_REST_REQUEST_URI_INVALID']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return true;
}

if (str_starts_with($path, '/api/refbook')) {
    require __DIR__ . '/../../public/api/refbook.php';
    return true;
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => ['code' => 'OPUS_REFBOOK_REST_NOT_FOUND', 'message' => 'Unknown Opus REST endpoint.']], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
return true;
