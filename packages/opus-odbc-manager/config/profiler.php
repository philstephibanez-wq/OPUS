<?php
declare(strict_types=1);

return [
    'contract' => 'OPUS_ODBC_MANAGER_PROFILER_CONFIG_V1',
    'category' => 'opus.odbc_manager',
    'actions' => [
        'dashboard',
        'datasources',
        'tables',
        'table_detail',
        'preview',
        'lstsar_draft',
        'crud_overview',
        'crud_insert_form',
        'crud_update_form',
        'crud_delete_form',
        'crud_dry_run',
    ],
    'redact' => [
        'password',
        'pass',
        'secret',
        'token',
        'api_key',
        'apikey',
        'authorization',
        'confirmation',
        'confirmation_token',
    ],
];
