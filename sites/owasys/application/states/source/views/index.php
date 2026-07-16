<?php
declare(strict_types=1);

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/source'), PHP_URL_PATH);
$requestPath = is_string($requestPath) ? $requestPath : '/source';
$sourceAssetBase = str_starts_with($requestPath, '/owasys/') ? '/owasys' : '';
echo '<script defer src="' . htmlspecialchars($sourceAssetBase . '/asset/js/source-editor.js', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></script>';

return [
    'state' => 'source',
    'title' => 'Source & Git',
    'badge' => 'Application source editor',
    'summary' => 'Inspect the selected application repository and edit authorized OPUS application files with preview, validation and atomic writes.',
    'sections' => ['Repository status', 'Application files', 'Editor', 'Diff preview', 'Validated write'],
    'contracts' => ['OWASYS_REPOSITORY_INSPECTION_V1', 'OWASYS_APPLICATION_FILE_EDITOR_V1'],
    'action_keys' => ['source.action.refresh', 'source.action.open', 'source.action.preview', 'source.action.save'],
];
