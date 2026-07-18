<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$site = $root . '/sites/owasys';
require $site . '/application/default/autoload.php';

use Owasys\Application\Http\FrontController;

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$cases = [
    '/' => '/',
    '/owasys' => '/',
    '/owasys/' => '/',
    '/owasys/build-action.php?x=1' => '/build-action.php',
    '/source-action.php' => '/source-action.php',
    '/owasys/structure-preview.php' => '/structure-preview.php',
    '/owasys/registry' => '/registry',
];

foreach ($cases as $requestUri => $expected) {
    $actual = FrontController::normalizePath($requestUri);
    if ($actual !== $expected) {
        $fail('OWASYS_FRONT_CONTROLLER_PATH_INVALID:' . $requestUri . ':' . $actual);
    }
}

$index = (string) file_get_contents($site . '/www/index.php');
if (str_contains($index, 'parse_url(') || str_contains($index, '$handlers =')) {
    $fail('OWASYS_FRONT_CONTROLLER_LOGIC_STILL_PUBLIC');
}
if (!str_contains($index, 'FrontController')) {
    $fail('OWASYS_FRONT_CONTROLLER_BOOTSTRAP_MISSING');
}
if (!str_contains($index, "'score-page.php'")) {
    $fail('OWASYS_FRONT_CONTROLLER_SCORE_DEFAULT_MISSING');
}
if (str_contains($index, "'application.php'")) {
    $fail('OWASYS_FRONT_CONTROLLER_LEGACY_DEFAULT_PRESENT');
}

$frontController = (string) file_get_contents($site . '/application/default/src/Http/FrontController.php');
if (!str_contains($frontController, "string \$defaultHandler = 'score-page.php'")) {
    $fail('OWASYS_FRONT_CONTROLLER_SCORE_CONSTRUCTOR_DEFAULT_MISSING');
}
if (str_contains($frontController, "'application.php'")) {
    $fail('OWASYS_FRONT_CONTROLLER_LEGACY_HANDLER_PRESENT');
}

$publicPhp = glob($site . '/www/*.php') ?: [];
if (count($publicPhp) !== 1 || basename($publicPhp[0]) !== 'index.php') {
    $fail('OWASYS_FRONT_CONTROLLER_PUBLIC_PHP_INVALID');
}

echo 'OWASYS_FRONT_CONTROLLER_BOUNDARY_SMOKE_OK' . PHP_EOL;
