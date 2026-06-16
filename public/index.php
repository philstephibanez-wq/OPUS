<?php
declare(strict_types=1);

/**
 * OPUS public front controller for Apache/UwAmp.
 *
 * Contract:
 * - this file is the only document-root entrypoint for HTTP servers;
 * - Apache/UwAmp must point DocumentRoot to OPUS/public, never to OPUS root;
 * - the sovereign runtime entrypoint remains ../index.php;
 * - public failures are neutral and do not expose internal diagnostics.
 */
$opusRoot = dirname(__DIR__);
$entrypoint = $opusRoot . DIRECTORY_SEPARATOR . 'index.php';

try {
    if (!is_file($entrypoint)) {
        throw new RuntimeException('OPUS_PUBLIC_FRONT_CONTROLLER_ROOT_ENTRYPOINT_MISSING');
    }

    require $entrypoint;
} catch (Throwable $exception) {
    error_log('OPUS_PUBLIC_FRONT_CONTROLLER_FAILURE: ' . $exception->getMessage());

    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
    }

    echo "Site temporairement bloquÃ©.\nContactez le support.";
    exit(1);
}