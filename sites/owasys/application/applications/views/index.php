<?php
declare(strict_types=1);

/**
 * OWASYS applications view-model.
 * Data only: RefBook/UserBook are application blueprints, not internal pages.
 */
return [
    'title' => 'Applications',
    'badge' => 'Registry',
    'summary' => 'Registry of OPUS sites and packages managed by OWASYS.',
    'sections' => [
        'Application types',
        'Blueprints',
        'Git and Composer metadata',
    ],
    'cards' => [
        [
            'title' => 'Managed targets',
            'body' => 'OWASYS manages standard OPUS targets through configuration and registry contracts.',
            'items' => [
                'fullstack OPUS application',
                'frontend OPUS application',
                'backend OPUS application',
                'OPUS package',
            ],
        ],
        [
            'title' => 'Documentation apps',
            'body' => 'RefBook and UserBook are generated as separate OPUS sites when needed; they are not OWASYS internal sections.',
            'items' => [
                'opus-refbook-app blueprint',
                'opus-userbook-app blueprint',
            ],
        ],
    ],
    'contracts' => [
        'OWASYS_APPLICATION_TYPES_V1',
        'OWASYS_REGISTRY_SEED_V1',
        'OPUS_MODEL_SCHEMA_V1',
    ],
    'actions' => [
        'Register an existing OPUS application',
        'Create a new application draft',
        'Attach Git remote and Composer metadata',
    ],
];
