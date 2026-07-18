<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$site = $root . '/sites/owasys';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$forbiddenPaths = [
    'application/application.php',
    'application/default/http',
    'application/default/security',
    'www/build-action.php',
    'www/source-action.php',
    'www/structure-preview.php',
];

foreach ($forbiddenPaths as $relative) {
    if (file_exists($site . '/' . $relative)) {
        $fail('OWASYS_LEGACY_PATH_PRESENT:' . $relative);
    }
}

$scanRoots = [
    $site . '/application',
    $site . '/www',
];
$forbiddenMarkers = [
    'ow-shell',
    'ow-sidebar',
    'class="ow-nav"',
    'OWASYS_LEGACY_APPLICATION_REMOVED',
    "'application.php'",
    'mermaid.min.js',
];

foreach ($scanRoots as $scanRoot) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        $extension = strtolower($fileInfo->getExtension());
        if (!in_array($extension, ['php', 'score', 'css', 'js', 'json', 'md'], true)) {
            continue;
        }
        $source = file_get_contents($fileInfo->getPathname());
        if (!is_string($source)) {
            $fail('OWASYS_SOURCE_UNREADABLE:' . $fileInfo->getPathname());
        }
        foreach ($forbiddenMarkers as $marker) {
            if (str_contains($source, $marker)) {
                $relative = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($site) + 1));
                $fail('OWASYS_LEGACY_MARKER_PRESENT:' . $relative . ':' . $marker);
            }
        }
    }
}

$index = (string) file_get_contents($site . '/www/index.php');
if (!str_contains($index, "'score-page.php'")) {
    $fail('OWASYS_SCORE_DEFAULT_HANDLER_MISSING');
}

$frontController = (string) file_get_contents($site . '/application/default/src/Http/FrontController.php');
if (!str_contains($frontController, "string \$defaultHandler = 'score-page.php'")) {
    $fail('OWASYS_SCORE_FRONT_CONTROLLER_DEFAULT_MISSING');
}
if (!str_contains($frontController, 'OWASYS_METHOD_NOT_SUPPORTED')) {
    $fail('OWASYS_NON_GET_FAIL_CLOSED_MISSING');
}

echo 'OWASYS_NO_LEGACY_SMOKE_OK' . PHP_EOL;
