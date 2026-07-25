<?php
declare(strict_types=1);

$mode = getenv('OPUS_APPLICATION_RUNTIME_MODE');
$mode = is_string($mode) ? strtolower(trim($mode)) : '';
$bootstrap = match ($mode) {
    'front' => dirname(__DIR__) . '/application/front/bootstrap.php',
    'back' => dirname(__DIR__) . '/application/back/bootstrap.php',
    default => null,
};
if (!is_string($bootstrap) || !is_file($bootstrap)) {
    http_response_code(500);
    $stream = fopen('php://output', 'wb');
    if ($stream !== false) {
        fwrite($stream, 'OWASYS_RUNTIME_BOOTSTRAP_UNAVAILABLE');
        fclose($stream);
    }
    return true;
}

return require $bootstrap;
