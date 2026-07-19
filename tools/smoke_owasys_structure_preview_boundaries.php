<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$site = $root . '/sites/owasys';
$action = $site . '/application/states/structure/actions/structure-preview.php';
$template = $site . '/application/states/structure/templates/preview-result.score';

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

foreach ([$action, $template] as $file) {
    if (!is_file($file)) {
        $fail('OWASYS_STRUCTURE_PREVIEW_FILE_MISSING:' . $file);
    }
}

$source = file_get_contents($action);
if (!is_string($source)) {
    $fail('OWASYS_STRUCTURE_PREVIEW_SOURCE_UNREADABLE');
}

foreach ([
    'SiteConfiguration::load',
    'new SessionContext',
    'Translator::load',
    'new ScoreTemplateRenderer',
    "render('preview-result.score'",
    '$session->user()',
    '$session->currentApplication()',
    'StructureDraftWritePlanner::forOpusRoot',
    'StructureDraftPreviewConfirmation::persist',
] as $required) {
    if (!str_contains($source, $required)) {
        $fail('OWASYS_STRUCTURE_PREVIEW_BOUNDARY_NOT_WIRED:' . $required);
    }
}

foreach ([
    "json_decode((string) file_get_contents(\$siteRoot . '/config/site.json')",
    'session_name(',
    'session_start(',
    'application/default/local/',
    "\$_SESSION['owasys_user']",
    "\$_SESSION['owasys_current_app']",
    "echo '<section",
    '$html .=',
] as $forbidden) {
    if (str_contains($source, $forbidden)) {
        $fail('OWASYS_STRUCTURE_PREVIEW_DUPLICATION_PRESENT:' . $forbidden);
    }
}

$templateSource = file_get_contents($template);
if (!is_string($templateSource)) {
    $fail('OWASYS_STRUCTURE_PREVIEW_TEMPLATE_UNREADABLE');
}
foreach ([
    'OWASYS_STRUCTURE_WRITE_PLAN_RESULT',
    'OWASYS_STRUCTURE_WRITE_PLAN_STATUS',
    'OWASYS_STRUCTURE_PREVIEW_CONFIRMED',
    'OWASYS_STRUCTURE_WRITE_PLAN_FILE',
    'preview.files',
    'preview.collisions',
] as $required) {
    if (!str_contains($templateSource, $required)) {
        $fail('OWASYS_STRUCTURE_PREVIEW_TEMPLATE_MARKER_MISSING:' . $required);
    }
}
if (str_contains($templateSource, '<?')) {
    $fail('OWASYS_STRUCTURE_PREVIEW_TEMPLATE_PHP_FORBIDDEN');
}

echo 'OWASYS_STRUCTURE_PREVIEW_BOUNDARIES_SMOKE_OK' . PHP_EOL;
