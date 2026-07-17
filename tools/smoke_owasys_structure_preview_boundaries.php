<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$site = $root . '/sites/owasys';
$file = $site . '/application/states/structure/actions/structure-preview.php';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$source = file_get_contents($file);
if (!is_string($source)) {
    $fail('OWASYS_STRUCTURE_PREVIEW_SOURCE_UNREADABLE');
}

foreach ([
    'SiteConfiguration::load',
    'new SessionContext',
    'Translator::load',
    '$session->user()',
    '$session->currentApplication()',
] as $required) {
    if (!str_contains($source, $required)) {
        $fail('OWASYS_STRUCTURE_PREVIEW_BOUNDARY_NOT_WIRED:' . $required);
    }
}

foreach ([
    "json_decode((string) file_get_contents($siteRoot . '/config/site.json')",
    'session_name(',
    'session_start(',
    "application/default/local/",
    "$_SESSION['owasys_user']",
    "$_SESSION['owasys_current_app']",
] as $forbidden) {
    if (str_contains($source, $forbidden)) {
        $fail('OWASYS_STRUCTURE_PREVIEW_DUPLICATION_PRESENT:' . $forbidden);
    }
}

echo 'OWASYS_STRUCTURE_PREVIEW_BOUNDARIES_SMOKE_OK' . PHP_EOL;
