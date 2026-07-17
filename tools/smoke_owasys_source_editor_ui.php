<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$view = (string) file_get_contents($root . '/sites/owasys/application/states/source/views/index.php');
$script = (string) file_get_contents($root . '/sites/owasys/www/asset/js/source-editor.js');

$requiredViewMarkers = [
    'asset/js/source-editor.js',
    "'state' => 'source'",
    'OWASYS_REPOSITORY_INSPECTION_V1',
    'OWASYS_APPLICATION_FILE_EDITOR_V1',
];
foreach ($requiredViewMarkers as $marker) {
    if (!str_contains($view, $marker)) {
        fwrite(STDERR, "OWASYS_SOURCE_EDITOR_UI_VIEW_MARKER_MISSING: {$marker}\n");
        exit(1);
    }
}

$requiredScriptMarkers = [
    'OWASYS_SOURCE_EDITOR_UI',
    'OWASYS_SOURCE_FILE_TREE',
    'OWASYS_SOURCE_CONTENT_EDITOR',
    'expected_sha256',
    'OWASYS_SOURCE_PREVIEW_REQUIRED',
    'window.confirm',
];
foreach ($requiredScriptMarkers as $marker) {
    if (!str_contains($script, $marker)) {
        fwrite(STDERR, "OWASYS_SOURCE_EDITOR_UI_SCRIPT_MARKER_MISSING: {$marker}\n");
        exit(1);
    }
}

$requiredActions = [
    'list',
    'read',
    'preview',
    'write',
    'git-diff',
];
foreach ($requiredActions as $action) {
    $pattern = "/action\\s*:\\s*['\"]" . preg_quote($action, '/') . "['\"]/";
    if (preg_match($pattern, $script) !== 1) {
        fwrite(STDERR, "OWASYS_SOURCE_EDITOR_UI_ACTION_MARKER_MISSING: {$action}\n");
        exit(1);
    }
}

$forbiddenExecutableMarkers = [
    "action: 'git-push'",
    "action: 'git-pull'",
    "action: 'git-reset'",
    'git push ',
    'git pull ',
    'git reset ',
    'eval(',
];
foreach ($forbiddenExecutableMarkers as $marker) {
    if (stripos($script, $marker) !== false) {
        fwrite(STDERR, "OWASYS_SOURCE_EDITOR_UI_FORBIDDEN_EXECUTABLE_MARKER: {$marker}\n");
        exit(1);
    }
}

if (!str_contains($script, 'No push, pull, reset or arbitrary command is available.')) {
    fwrite(STDERR, "OWASYS_SOURCE_EDITOR_UI_SAFETY_NOTICE_MISSING\n");
    exit(1);
}

echo "OWASYS_SOURCE_EDITOR_UI_SMOKE_OK\n";
