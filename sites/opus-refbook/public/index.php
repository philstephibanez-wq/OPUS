<?php
declare(strict_types=1);

/*
 * Public front controller.
 *
 * Contract:
 * - bootstrap only
 * - no routing decision here
 * - no template rendering here
 * - no Markdown API rendering here
 */

$appRoot = dirname(__DIR__);

require_once $appRoot . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'autoload.php';

try {
    $paths = new Opus\Application\ApplicationPaths($appRoot, 'opus-reference');
    $request = Opus\Http\Request::fromGlobals();
    $response = (new Opus\Application\Application($paths))->run($request);
    $response->send();
} catch (Throwable $exception) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'OPUS_REFBOOK_RUNTIME_ERROR' . PHP_EOL . $exception->getMessage();
}
