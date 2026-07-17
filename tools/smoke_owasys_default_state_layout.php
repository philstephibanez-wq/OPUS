<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$site = $root . '/sites/owasys';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

if (is_dir($site . '/application/default/http')) {
    $fail('OWASYS_DEFAULT_HTTP_DIRECTORY_FORBIDDEN');
}

$required = [
    'application/states/build/actions/build-action.php',
    'application/states/source/actions/source-action.php',
    'application/states/structure/actions/structure-preview.php',
    'application/application.php',
    'WORKSPACE.md',
];

foreach ($required as $relative) {
    if (!is_file($site . '/' . $relative)) {
        $fail('OWASYS_APPLICATION_LAYOUT_MISSING:' . $relative);
    }
}

$index = (string) file_get_contents($site . '/www/index.php');
foreach ([
    'states/build/actions/build-action.php',
    'states/source/actions/source-action.php',
    'states/structure/actions/structure-preview.php',
] as $handler) {
    if (!str_contains($index, $handler)) {
        $fail('OWASYS_STATE_HANDLER_NOT_WIRED:' . $handler);
    }
}

$workspace = (string) file_get_contents($site . '/WORKSPACE.md');
if (!str_contains($workspace, '`application/default` is the common presentation layer')) {
    $fail('OWASYS_WORKSPACE_DEFAULT_CONTRACT_MISSING');
}
if (!str_contains($workspace, 'must not contain HTTP entrypoints')) {
    $fail('OWASYS_WORKSPACE_HTTP_PROHIBITION_MISSING');
}

echo 'OWASYS_DEFAULT_STATE_LAYOUT_SMOKE_OK' . PHP_EOL;
