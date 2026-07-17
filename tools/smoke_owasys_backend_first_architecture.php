<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$site = $root . '/sites/owasys';
$entry = $site . '/www/index.php';
$bootstrap = $site . '/application/default/bootstrap.php';
$themeJs = $site . '/www/asset/themes/owasys/js/theme.js';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

foreach ([$entry, $themeJs] as $requiredFile) {
    if (!is_file($requiredFile)) {
        $fail('OWASYS_BACKEND_FIRST_REQUIRED_FILE_MISSING:' . $requiredFile);
    }
}

if (!is_file($bootstrap)) {
    $fail('OWASYS_BACKEND_FIRST_BOOTSTRAP_MISSING');
}

$entrySource = (string) file_get_contents($entry);
$bootstrapRequire = "require dirname(__DIR__) . '/application/default/bootstrap.php';";

if (!str_contains($entrySource, $bootstrapRequire)) {
    $fail('OWASYS_BACKEND_FIRST_ENTRYPOINT_BOOTSTRAP_CALL_MISSING');
}

$entryLines = preg_split('/\R/', trim($entrySource)) ?: [];
if (count($entryLines) > 8) {
    $fail('OWASYS_BACKEND_FIRST_ENTRYPOINT_NOT_MINIMAL');
}

foreach ([
    'echo ',
    'print ',
    '<!doctype',
    '<html',
    '<nav',
    '<form',
    'session_start',
    'FsmSiteLoader',
    'RegistryRepository',
    'application/default/local',
] as $forbiddenMarker) {
    if (stripos($entrySource, $forbiddenMarker) !== false) {
        $fail('OWASYS_BACKEND_FIRST_ENTRYPOINT_FORBIDDEN_MARKER:' . $forbiddenMarker);
    }
}

$themeSource = (string) file_get_contents($themeJs);
foreach ([
    'languageLabels',
    "document.createElement('header')",
    "document.createElement('nav')",
    'globalNav.appendChild',
    'actions.appendChild(currentApplication)',
    'sidebar?.remove()',
    'panel.remove()',
] as $forbiddenMarker) {
    if (str_contains($themeSource, $forbiddenMarker)) {
        $fail('OWASYS_BACKEND_FIRST_JAVASCRIPT_OWNS_LAYOUT:' . $forbiddenMarker);
    }
}

$navigationFile = $site . '/application/default/navigation/menu.php';
$layoutFile = $site . '/application/default/layouts/main.php';
if (!is_file($navigationFile)) {
    $fail('OWASYS_BACKEND_FIRST_SERVER_NAVIGATION_MISSING');
}
if (!is_file($layoutFile)) {
    $fail('OWASYS_BACKEND_FIRST_SERVER_LAYOUT_MISSING');
}

$navigationSource = (string) file_get_contents($navigationFile);
if (!str_contains($navigationSource, 'label_key')) {
    $fail('OWASYS_BACKEND_FIRST_NAVIGATION_I18N_CONTRACT_MISSING');
}

$layoutSource = (string) file_get_contents($layoutFile);
if (!str_contains($layoutSource, '<nav') || !str_contains($layoutSource, '<html')) {
    $fail('OWASYS_BACKEND_FIRST_LAYOUT_SERVER_RENDERING_MISSING');
}

if (preg_match('/\becho\s+[\'\"]\s*</', $layoutSource) === 1) {
    $fail('OWASYS_BACKEND_FIRST_LAYOUT_HTML_ECHO_FORBIDDEN');
}

echo "OWASYS_BACKEND_FIRST_ARCHITECTURE_SMOKE_OK\n";
