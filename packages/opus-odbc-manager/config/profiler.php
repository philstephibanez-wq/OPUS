<?php
declare(strict_types=1);

return [
    'contract' => 'OPUS_ODBC_MANAGER_PROFILER_CONFIG_V1',
    'category' => 'opus.odbc_manager',
    'mode' => 'readonly',
    'events' => [
        'action.started',
        'action.finished',
        'action.failed',
    ],
    'actions' => [
        'dashboard',
        'datasources',
        'tables',
        'table_detail',
        'preview',
        'lstsar_draft',
    ],
    'redaction' => [
        'password',
        'secret',
        'token',
        'api_key',
        'authorization',
    ],
];
