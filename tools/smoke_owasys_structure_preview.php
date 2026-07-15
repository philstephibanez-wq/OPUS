<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';
$endpointFile = $siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'structure-preview.php';
$jsFile = $siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'asset' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'owasys.js';
$frFile = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR . 'fr.php';
$enFile = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR . 'en.php';
$confirmationFile = $root . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Owasys' . DIRECTORY_SEPARATOR . 'StructureDraftPreviewConfirmation.php';

foreach ([$endpointFile, $frFile, $enFile, $confirmationFile, __FILE__] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "OWASYS_STRUCTURE_PREVIEW_REQUIRED_FILE_MISSING: {$file}\n");
        exit(1);
    }
    $output = [];
    $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, "OWASYS_STRUCTURE_PREVIEW_PARSE_ERROR: {$file}\n" . implode("\n", $output) . "\n");
        exit(1);
    }
}
if (!is_file($jsFile)) {
    fwrite(STDERR, "OWASYS_STRUCTURE_PREVIEW_JS_FILE_MISSING\n");
    exit(1);
}

$endpoint = (string) file_get_contents($endpointFile);
foreach ([
    'StructureDraftPreviewConfirmation',
    'StructureDraftWritePlanner',
    'StructureDraftRepository::forRegistry',
    'recentDrafts',
    'planAddStateDraft',
    'OWASYS_STRUCTURE_WRITE_PLAN_RESULT',
    'OWASYS_STRUCTURE_WRITE_PLAN_STATUS',
    'OWASYS_STRUCTURE_WRITE_PLAN_FILE',
    'OWASYS_STRUCTURE_PREVIEW_CONFIRMED',
    'owasys_draft_id',
    'draft.preview_result',
    'draft.preview_status',
    'draft.preview_error',
    'draft.preview_collisions',
    'draft.preview_confirmed',
] as $needle) {
    if (!str_contains($endpoint, $needle)) {
        fwrite(STDERR, "OWASYS_STRUCTURE_PREVIEW_ENDPOINT_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

$confirmation = (string) file_get_contents($confirmationFile);
foreach ([
    'OWASYS_STRUCTURE_DRAFT_PREVIEW_CONFIRMATION_V1',
    'planHash',
    'assertConfirmed',
    'structure_preview:',
    'OWASYS_STRUCTURE_DRAFT_APPLY_PREVIEW_CONFIRMATION_MISSING',
    'OWASYS_STRUCTURE_DRAFT_APPLY_PREVIEW_CONFIRMATION_PLAN_CHANGED',
] as $needle) {
    if (!str_contains($confirmation, $needle)) {
        fwrite(STDERR, "OWASYS_STRUCTURE_PREVIEW_CONFIRMATION_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

$js = (string) file_get_contents($jsFile);
foreach ([
    'structure-preview.php',
    'preview-structure-draft',
    'OWASYS_STRUCTURE_WRITE_PLAN_FORM',
    'OWASYS_STRUCTURE_WRITE_PLAN',
    'OWASYS_STRUCTURE_APPLY_DRAFT_FORM',
    'fetch(previewForm.action',
] as $needle) {
    if (!str_contains($js, $needle)) {
        fwrite(STDERR, "OWASYS_STRUCTURE_PREVIEW_JS_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

foreach (['fr' => $frFile, 'en' => $enFile] as $locale => $file) {
    $messages = require $file;
    if (!is_array($messages)) {
        fwrite(STDERR, "OWASYS_STRUCTURE_PREVIEW_I18N_INVALID: {$locale}\n");
        exit(1);
    }
    foreach (['draft.preview_result', 'draft.preview_status', 'draft.preview_error', 'draft.preview_collisions', 'draft.preview_confirmed'] as $key) {
        if (!isset($messages[$key]) || !is_string($messages[$key]) || trim($messages[$key]) === '') {
            fwrite(STDERR, "OWASYS_STRUCTURE_PREVIEW_I18N_KEY_MISSING: {$locale}:{$key}\n");
            exit(1);
        }
    }
}

foreach (['Server plan</h2>', 'Preview server plan</button>', 'Draft server plan</h2>'] as $forbidden) {
    if (str_contains($endpoint, $forbidden)) {
        fwrite(STDERR, "OWASYS_STRUCTURE_PREVIEW_HARDCODED_LITERAL_PRESENT: {$forbidden}\n");
        exit(1);
    }
}

echo "OWASYS_STRUCTURE_PREVIEW_SMOKE_OK\n";
