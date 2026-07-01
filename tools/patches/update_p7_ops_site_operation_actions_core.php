<?php
declare(strict_types=1);

$root = getcwd();
$publicDir = $root . '/sites/opus-p7-ops/public';
$indexFile = $publicDir . '/index.php';
$routerFile = $publicDir . '/router.php';
$actionFile = $publicDir . '/action.php';

foreach ([$indexFile, $routerFile, $actionFile] as $requiredFile) {
    if (!is_file($requiredFile)) {
        fwrite(STDERR, 'OPS_REQUIRED_FILE_NOT_FOUND: ' . $requiredFile . PHP_EOL);
        exit(1);
    }
}

$routerSource = <<<'PHPROUTER'
<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = $path === '/' ? '/' : rtrim($path, '/');

if ($path === '/opus-lstsar-manager/action') {
    require __DIR__ . '/action.php';
    return true;
}

$requestedFile = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);
if ($path !== '/' && is_file($requestedFile)) {
    return false;
}

require __DIR__ . '/index.php';
return true;
PHPROUTER;

if (file_put_contents($routerFile, $routerSource) === false) {
    fwrite(STDERR, 'OPS_ROUTER_WRITE_FAILED' . PHP_EOL);
    exit(1);
}

$indexSource = file_get_contents($indexFile);
if ($indexSource === false) {
    fwrite(STDERR, 'OPS_INDEX_READ_FAILED' . PHP_EOL);
    exit(1);
}

if (!str_contains($indexSource, '/opus-lstsar-manager/action?site=')) {
    $updatedIndex = str_replace('href="?site=', 'href="/opus-lstsar-manager/action?site=', $indexSource);
    if ($updatedIndex === $indexSource) {
        fwrite(STDERR, 'OPS_ACTION_LINK_ANCHOR_NOT_FOUND' . PHP_EOL);
        exit(1);
    }

    if (file_put_contents($indexFile, $updatedIndex) === false) {
        fwrite(STDERR, 'OPS_INDEX_WRITE_FAILED' . PHP_EOL);
        exit(1);
    }
}

echo 'P7_OPS_SITE_OPERATION_ACTIONS_CORE_UPDATED' . PHP_EOL;
