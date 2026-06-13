<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'Opus\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = $root . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

try {
    $provider = new ASAP\RefBook\Api\RefBookRestSnapshotProvider($root);
    $assets = new ASAP\RefBook\Api\RefBookDocumentationAssetRepository($root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'refbook');
    $api = new ASAP\RefBook\Api\RefBookRestApi($provider, $assets);
    $api->handle(Opus\Http\Request::fromGlobals())->send();
} catch (Throwable $error) {
    ASAP\Http\Response::json([
        'ok' => false,
        'error' => [
            'code' => 'OPUS_REFBOOK_REST_BOOT_FAILED',
            'message' => $error->getMessage(),
        ],
    ], 500)->send();
}
