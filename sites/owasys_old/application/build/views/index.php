<?php
declare(strict_types=1);

/**
 * OWASYS build view-model.
 * Data only: build and validation remain explicit OPUS commands/contracts.
 */
return [
    'title' => 'Build & Validate',
    'badge' => 'Application creator',
    'summary' => 'Generate, validate and export OPUS application skeletons through the OWASYS creator pipeline.',
    'sections' => [
        'Creation request',
        'Scaffold plan',
        'Dry-run',
        'Write',
        'Validation',
        'Manifest',
        'Export',
    ],
    'cards' => [
        [
            'title' => 'ApplicationCreator',
            'body' => 'OWASYS now has an orchestrator that runs request validation, scaffold planning, mandatory dry-run, optional write, validation and creation manifest.',
            'items' => [
                'Opus/Owasys/ApplicationCreator.php',
                'tools/owasys_create_application.php',
                'default mode: dry-run',
            ],
        ],
        [
            'title' => 'Scaffold plan',
            'body' => 'Generation remains split into a plan phase before write operations, so directories and files can be validated first.',
            'items' => [
                'Opus/Owasys/ScaffoldPlanBuilder.php',
                'config/scaffold-plan.schema.json',
                'forbidden roots: public, src, resources',
            ],
        ],
        [
            'title' => 'Scaffold writer',
            'body' => 'The writer refuses existing targets and never overwrites files. Actual write requires an explicit --write flag.',
            'items' => [
                'Opus/Owasys/ApplicationScaffoldWriter.php',
                'tools/owasys_write_scaffold_plan.php',
                'no implicit mutation',
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
        'OWASYS_APPLICATION_CREATION_RESULT_V1',
        'OWASYS_CREATE_APPLICATION_CONTRACT_V1',
        'OWASYS_SCAFFOLD_PLAN_SCHEMA_V1',
        'OWASYS_EXPORT_POLICY_V1',
        'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
    ],
    'actions' => [
        'Validate application creation request',
        'Generate scaffold plan',
        'Run mandatory dry-run',
        'Write standard OPUS site tree only with --write',
        'Run OPUS site validation',
        'Create clean creation manifest',
    ],
];
