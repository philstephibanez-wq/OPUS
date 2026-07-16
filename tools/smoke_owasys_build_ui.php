<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$endpoint = $root . '/sites/owasys/www/build-action.php';
$javascript = $root . '/sites/owasys/www/asset/js/owasys.js';
$view = $root . '/sites/owasys/application/states/build/views/index.php';

foreach ([$endpoint, $javascript, $view] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, 'OWASYS_BUILD_UI_REQUIRED_FILE_MISSING:' . str_replace('\\', '/', $path) . "\n");
        exit(1);
    }
}

$endpointSource = (string) file_get_contents($endpoint);
foreach ([
    'OWASYS_BUILD_HTTP_RESULT_V1',
    'OWASYS_BUILD_PIPELINE',
    'new BuildPipeline($opusRoot)',
    'OWASYS_AUTHENTICATION_REQUIRED',
    'HTTP_X_OWASYS_BUILD',
] as $marker) {
    if (!str_contains($endpointSource, $marker)) {
        fwrite(STDERR, 'OWASYS_BUILD_UI_ENDPOINT_MARKER_MISSING:' . $marker . "\n");
        exit(1);
    }
}

$javascriptSource = (string) file_get_contents($javascript);
foreach ([
    'OWASYS_BUILD_PIPELINE_UI',
    'OWASYS_BUILD_PIPELINE_RESULT',
    'build-action.php',
    "['preview', 'Preview']",
    "['build', 'Generate & validate']",
    "['build-and-export', 'Generate, validate & export']",
    "document.body.dataset.opusState === 'build'",
] as $marker) {
    if (!str_contains($javascriptSource, $marker)) {
        fwrite(STDERR, 'OWASYS_BUILD_UI_JAVASCRIPT_MARKER_MISSING:' . $marker . "\n");
        exit(1);
    }
}

$viewModel = require $view;
if (!is_array($viewModel) || ($viewModel['state'] ?? null) !== 'build') {
    fwrite(STDERR, "OWASYS_BUILD_UI_VIEW_MODEL_INVALID\n");
    exit(1);
}

echo "OWASYS_BUILD_UI_SMOKE_OK\n";
