<?php
declare(strict_types=1);

/**
 * OWASYS build view-model.
 * Data only: build and validation remain explicit OPUS commands/contracts.
 */
return [
    'title' => 'Build & Validate',
    'badge' => 'Build',
    'summary' => 'Generation, validation and export of OPUS application skeletons.',
    'sections' => [
        'Skeleton generation',
        'Validation',
        'Manifest',
        'Export',
    ],
    'cards' => [
        [
            'title' => 'Validation commands',
            'body' => 'OWASYS keeps generated site validation explicit and reproducible.',
            'items' => [
                'php tools/smoke_opus_site_contract_eternal.php',
                'php bin/opus validate:site <site>',
            ],
        ],
        [
            'title' => 'Clean deliverables',
            'body' => 'Generated deliverables must exclude caches, temporary extraction folders and runtime mutable databases.',
            'items' => [
                'no cache',
                'no temporary folders',
                'runtime SQLite excluded',
            ],
        ],
    ],
    'contracts' => [
        'OWASYS_VALIDATION_POLICY_V1',
        'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
    ],
    'actions' => [
        'Generate skeleton',
        'Run validation',
        'Create manifest',
        'Create export zip',
    ],
];
