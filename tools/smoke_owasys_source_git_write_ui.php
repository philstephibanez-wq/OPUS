<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$script = $root . '/sites/owasys/www/asset/js/source-editor.js';
$endpoint = $root . '/sites/owasys/www/source-action.php';
$operator = $root . '/Opus/Owasys/RepositoryOperator.php';

foreach ([$script, $endpoint, $operator] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, 'OWASYS_SOURCE_GIT_WRITE_UI_REQUIRED_FILE_MISSING: ' . $file . "\n");
        exit(1);
    }
}

$js = (string) file_get_contents($script);
$php = (string) file_get_contents($endpoint);
$operatorPhp = (string) file_get_contents($operator);

$requiredJsMarkers = [
    'OWASYS_SOURCE_GIT_WRITE_UI',
    'OWASYS_SOURCE_GIT_COMMIT_MESSAGE',
    'OWASYS_SOURCE_GIT_STAGE',
    'OWASYS_SOURCE_GIT_COMMIT',
    'git-stage-application',
    'git-commit-application',
    'Prepare only the selected application changes for commit?',
    'OWASYS_SOURCE_GIT_STAGE_RUNNING',
    'OWASYS_SOURCE_GIT_COMMIT_RUNNING',
];
foreach ($requiredJsMarkers as $marker) {
    if (!str_contains($js, $marker)) {
        fwrite(STDERR, 'OWASYS_SOURCE_GIT_WRITE_UI_MARKER_MISSING: ' . $marker . "\n");
        exit(1);
    }
}

foreach (['git-stage-application', 'git-commit-application', 'RepositoryOperator'] as $marker) {
    if (!str_contains($php, $marker)) {
        fwrite(STDERR, 'OWASYS_SOURCE_GIT_WRITE_ENDPOINT_MARKER_MISSING: ' . $marker . "\n");
        exit(1);
    }
}

foreach (['stageApplication', 'commitApplication', "'push_performed' => false", "'arbitrary_command' => false"] as $marker) {
    if (!str_contains($operatorPhp, $marker)) {
        fwrite(STDERR, 'OWASYS_SOURCE_GIT_OPERATOR_MARKER_MISSING: ' . $marker . "\n");
        exit(1);
    }
}

$forbiddenUiActions = ['git-push', 'git-pull', 'git-reset', 'git-checkout', 'arbitrary-command'];
foreach ($forbiddenUiActions as $marker) {
    if (str_contains($js, $marker) || str_contains($php, $marker)) {
        fwrite(STDERR, 'OWASYS_SOURCE_GIT_WRITE_UI_FORBIDDEN_ACTION: ' . $marker . "\n");
        exit(1);
    }
}

echo "OWASYS_SOURCE_GIT_WRITE_UI_SMOKE_OK\n";
