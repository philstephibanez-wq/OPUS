<?php
declare(strict_types=1);

/**
 * OWASYS build view-model.
 * Data only: build and validation remain explicit OPUS commands/contracts.
 */
return [
    'title' => 'Build & Validate',
    'badge' => 'Creation contracts',
    'summary' => 'Generate, validate and export OPUS application skeletons through explicit OWASYS contracts.',
    'sections' => [
        'Creation request',
        'Scaffold plan',
        'Validation',
        'Manifest',
        'Export',
    ],
    'cards' => [
        [
            'title' => 'Creation request contract',
            'body' => 'OWASYS now has a typed contract for OPUS application creation requests before any file is generated.',
            'items' => [
                'config/create-application.contract.json',
                'application/default/models/application-request.model.json',
                'silent_fallback: false',
            ],
        ],
        [
            'title' => 'Scaffold plan',
            'body' => 'Generation is split into a plan phase before write operations, so directories and files can be validated first.',
            'items' => [
                'config/scaffold-plan.schema.json',
                'application/default/models/scaffold-plan.model.json',
                'forbidden roots: public, src, resources',
            ],
        ],
        [
            'title' => 'Clean export policy',
            'body' => 'Generated deliverables must exclude caches, temporary extraction folders, logs and runtime mutable databases.',
            'items' => [
                'config/export-policy.json',
                'runtime SQLite excluded',
                'manifest required',
            ],
        ],
    ],
    'contracts' => [
        'OWASYS_CREATE_APPLICATION_CONTRACT_V1',
        'OWASYS_SCAFFOLD_PLAN_SCHEMA_V1',
        'OWASYS_EXPORT_POLICY_V1',
        'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
    ],
    'actions' => [
        'Validate application creation request',
        'Generate scaffold plan',
        'Write standard OPUS site tree',
        'Run OPUS site validation',
        'Create clean export manifest and zip',
    ],
];
