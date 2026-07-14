<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$siteRoot = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'owasys';
$frontFile = $siteRoot . DIRECTORY_SEPARATOR . 'www' . DIRECTORY_SEPARATOR . 'index.php';
$frFile = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR . 'fr.php';
$enFile = $siteRoot . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR . 'en.php';
$draftRepositoryFile = $root . DIRECTORY_SEPARATOR . 'Opus' . DIRECTORY_SEPARATOR . 'Owasys' . DIRECTORY_SEPARATOR . 'StructureDraftRepository.php';

foreach ([$frontFile, $frFile, $enFile, $draftRepositoryFile, __FILE__] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "OWASYS_STRUCTURE_DRAFT_UI_REQUIRED_FILE_MISSING: {$file}\n");
        exit(1);
    }
    $output = [];
    $code = 0;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fwrite(STDERR, "OWASYS_STRUCTURE_DRAFT_UI_PARSE_ERROR: {$file}\n" . implode("\n", $output) . "\n");
        exit(1);
    }
}

$front = (string) file_get_contents($frontFile);
foreach ([
    'use Opus\\Owasys\\StructureDraftRepository;',
    'StructureDraftRepository::forRegistry',
    'prepare-add-state-draft',
    'prepareAddStateDraft',
    'recentDrafts',
    'owasys_state_id',
    'owasys_route_path',
    'owasys_title_key',
    'owasys_event_name',
    'OWASYS_STRUCTURE_ADD_STATE_DRAFT_FORM',
    'OWASYS_STRUCTURE_DRAFT_RESULT',
    'OWASYS_STRUCTURE_DRAFTS_RECENT',
    'draft.add_state_title',
    'draft.prepare',
    'draft.recent_title',
    'draft.disk_mutation_false',
] as $needle) {
    if (!str_contains($front, $needle)) {
        fwrite(STDERR, "OWASYS_STRUCTURE_DRAFT_UI_FRONT_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

foreach (['fr' => $frFile, 'en' => $enFile] as $locale => $file) {
    $messages = require $file;
    if (!is_array($messages)) {
        fwrite(STDERR, "OWASYS_STRUCTURE_DRAFT_UI_I18N_INVALID: {$locale}\n");
        exit(1);
    }
    foreach ([
        'draft.title',
        'draft.description',
        'draft.add_state_title',
        'draft.state_id',
        'draft.route_path',
        'draft.title_key',
        'draft.event_name',
        'draft.prepare',
        'draft.result',
        'draft.recent_title',
        'draft.empty',
        'draft.disk_mutation_false',
        'draft.error.invalid_request',
        'registry.events.draft_add_state',
    ] as $key) {
        if (!isset($messages[$key]) || !is_string($messages[$key]) || trim($messages[$key]) === '') {
            fwrite(STDERR, "OWASYS_STRUCTURE_DRAFT_UI_I18N_KEY_MISSING: {$locale}:{$key}\n");
            exit(1);
        }
    }
}

foreach ([
    'Prepare a new state',
    'Prepare draft</button>',
    'Recent drafts</h2>',
    'No disk write applied.',
] as $forbidden) {
    if (str_contains($front, $forbidden)) {
        fwrite(STDERR, "OWASYS_STRUCTURE_DRAFT_UI_HARDCODED_LITERAL_PRESENT: {$forbidden}\n");
        exit(1);
    }
}

$repositorySource = (string) file_get_contents($draftRepositoryFile);
foreach ([
    'OWASYS_STRUCTURE_DRAFTS_SQLITE_V1',
    'OWASYS_STRUCTURE_ADD_STATE_DRAFT_V1',
    'disk_mutation',
    'draft_add_state',
] as $needle) {
    if (!str_contains($repositorySource, $needle)) {
        fwrite(STDERR, "OWASYS_STRUCTURE_DRAFT_UI_REPOSITORY_MARKER_MISSING: {$needle}\n");
        exit(1);
    }
}

echo "OWASYS_STRUCTURE_DRAFT_UI_SMOKE_OK\n";
