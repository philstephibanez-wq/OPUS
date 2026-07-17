<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$entryPath = $root . '/assets-src/owasys-codemirror-entry.js';
$bundlePath = $root . '/sites/owasys/www/asset/vendor/codemirror/owasys-codemirror.js';
$viewPath = $root . '/sites/owasys/application/states/source/views/index.php';
$editorPath = $root . '/sites/owasys/www/asset/js/source-editor.js';

foreach ([$entryPath, $viewPath, $editorPath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, 'OWASYS_CODEMIRROR_REQUIRED_FILE_MISSING: ' . $path . "\n");
        exit(1);
    }
}

$entry = (string) file_get_contents($entryPath);
$view = (string) file_get_contents($viewPath);
$editor = (string) file_get_contents($editorPath);

$markers = [
    [$entry, 'OWASYS_CODEMIRROR_6_V1'],
    [$entry, 'lineNumbers()'],
    [$entry, 'autocompletion()'],
    [$entry, "lower.endsWith('.php')"],
    [$entry, "lower.endsWith('.json')"],
    [$entry, "lower.endsWith('.score')"],
    [$view, 'asset/vendor/codemirror/owasys-codemirror.js'],
    [$view, 'OWASYS_CODEMIRROR_6_V1'],
    [$editor, 'window.OWASYSCodeMirror'],
    [$editor, 'ow-source-workspace'],
    [$editor, 'ow-source-editor-host'],
    [$editor, 'editorAdapter.getValue()'],
];

foreach ($markers as [$content, $marker]) {
    if (!str_contains($content, $marker)) {
        fwrite(STDERR, 'OWASYS_CODEMIRROR_MARKER_MISSING: ' . $marker . "\n");
        exit(1);
    }
}

if (!is_file($bundlePath) || filesize($bundlePath) < 100000) {
    fwrite(STDERR, "OWASYS_CODEMIRROR_BUNDLE_MISSING_OR_TOO_SMALL\n");
    exit(1);
}

$bundle = (string) file_get_contents($bundlePath);
if (!str_contains($bundle, 'OWASYS_CODEMIRROR_6_V1')) {
    fwrite(STDERR, "OWASYS_CODEMIRROR_BUNDLE_CONTRACT_MISSING\n");
    exit(1);
}

if (str_contains($view, 'https://') || str_contains($view, 'http://')) {
    fwrite(STDERR, "OWASYS_CODEMIRROR_REMOTE_RUNTIME_DEPENDENCY_FORBIDDEN\n");
    exit(1);
}

echo "OWASYS_CODEMIRROR_SMOKE_OK\n";
