<?php
declare(strict_types=1);

return [
    'contract' => 'OPUS_LSTSAR_MANAGER_PROFILER_CONFIG_V1',
    'category' => 'opus.lstsar_manager',
    'actions' => [
        'dashboard',
        'declarations',
        'sources',
        'destinations',
        'mappings',
        'rules',
        'archive_report',
        'dry_run_form',
        'dry_run_preview',
    ],
    'redact' => [
        'password',
        'pass',
        'secret',
        'token',
        'api_key',
        'apikey',
        'authorization',
        'dsn_password',
        'connection_string',
    ],
];
