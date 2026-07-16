<?php
declare(strict_types=1);

return [
    'state' => 'source',
    'title' => 'Source & Git',
    'badge' => 'Application source editor',
    'summary' => 'Inspect the selected application repository and edit authorized OPUS application files with preview, validation and atomic writes.',
    'sections' => ['Repository status', 'Application files', 'Editor', 'Diff preview', 'Validated write'],
    'contracts' => ['OWASYS_REPOSITORY_INSPECTOR_V1', 'OWASYS_APPLICATION_FILE_EDITOR_V1'],
    'action_keys' => ['source.action.refresh', 'source.action.open', 'source.action.preview', 'source.action.save'],
];
