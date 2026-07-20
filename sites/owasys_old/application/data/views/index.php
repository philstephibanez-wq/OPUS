<?php
declare(strict_types=1);

/**
 * OWASYS data view-model.
 * Data only: model and datasource contracts remain explicit.
 */
return [
    'title' => 'Data Sources',
    'badge' => 'Models + ODBC',
    'summary' => 'Configuration of typed models, schemas, constraints, ODBC datasources and SQLite registry.',
    'sections' => [
        'Models',
        'Datasources',
        'SQLite schema',
        'Constraints',
    ],
    'cards' => [
        [
            'title' => 'Registry schema',
            'body' => 'The schema is committed as source of truth; the mutable SQLite database is runtime-only.',
            'items' => [
                'config/registry.schema.sql',
                'config/registry.seed.json',
                'var/registry/*.sqlite ignored',
            ],
        ],
        [
            'title' => 'No silent fallback',
            'body' => 'Datasource contracts must fail clearly when unavailable or invalid.',
            'items' => [
                'model_required: true',
                'silent_fallback: false',
                'access: model_then_odbc',
            ],
        ],
    ],
    'contracts' => [
        'OPUS_MODEL_SCHEMA_V1',
        'OPUS_DATASOURCE_REGISTRY_V1',
    ],
    'actions' => [
        'Add model schema',
        'Add datasource contract',
        'Validate field constraints',
        'Prepare runtime SQLite migration',
    ],
];
