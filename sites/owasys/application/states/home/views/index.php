<?php
declare(strict_types=1);

/**
 * OWASYS home state view-model.
 * Data only: no controller class, no wrapper, no hidden runtime dependency.
 */
return [
    'state' => 'home',
    'title' => 'Home',
    'badge' => 'Registry foundation',
    'summary' => 'OWASYS dashboard for OPUS application creation, configuration and validation.',
    'sections' => [
        'Current OWASYS site',
        'Registry status',
        'Next build actions',
    ],
    'cards' => [
        [
            'title' => 'Site identity',
            'body' => 'OWASYS is a standard OPUS application, not a wrapper and not a special runtime manager.',
            'items' => [
                'site_id: owasys',
                'contract: OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
                'states_root: application/states',
            ],
        ],
        [
            'title' => 'Registry foundation',
            'body' => 'Application metadata is described by committed models and schema files; the SQLite database remains runtime-only.',
            'items' => [
                'datasource: owasys_registry',
                'schema: config/registry.schema.sql',
                'runtime database: var/registry/owasys.sqlite',
            ],
        ],
    ],
    'contracts' => [
        'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
        'OWASYS_APPLICATION_TYPES_V1',
        'OWASYS_BLUEPRINT_REGISTRY_V1',
    ],
    'actions' => [
        'Register or draft an OPUS application',
        'Configure structure, data, workflow and security contracts',
        'Validate and export the generated application skeleton',
    ],
];