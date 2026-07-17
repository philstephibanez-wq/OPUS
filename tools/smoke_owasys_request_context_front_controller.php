<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$site = $root . '/sites/owasys';
require $site . '/application/default/autoload.php';

use Owasys\Application\Http\FrontController;
use Owasys\Application\Http\RequestContext;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

try {
    $cases = [
        ['/owasys', '/'],
        ['/owasys/', '/'],
        ['/owasys/build-action.php?x=1', '/build-action.php'],
        ['/source-action.php', '/source-action.php'],
    ];

    foreach ($cases as [$uri, $expected]) {
        $request = RequestContext::fromServer([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => $uri,
        ]);
        if ($request->path() !== $expected) {
            $fail('OWASYS_REQUEST_CONTEXT_PATH_INVALID:' . $uri);
        }
        if (FrontController::normalizePath($uri) !== $expected) {
            $fail('OWASYS_FRONT_CONTROLLER_PATH_INVALID:' . $uri);
        }
    }

    $frontControllerSource = (string) file_get_contents($site . '/application/default/src/Http/FrontController.php');
    if (!str_contains($frontControllerSource, 'RequestContext::fromServer')) {
        $fail('OWASYS_FRONT_CONTROLLER_REQUEST_CONTEXT_NOT_WIRED');
    }
    if (str_contains($frontControllerSource, 'parse_url(') || str_contains($frontControllerSource, 'rawurldecode(')) {
        $fail('OWASYS_FRONT_CONTROLLER_PATH_DUPLICATION_PRESENT');
    }
} catch (Throwable $exception) {
    $fail($exception->getMessage());
}

echo 'OWASYS_REQUEST_CONTEXT_FRONT_CONTROLLER_SMOKE_OK' . PHP_EOL;
