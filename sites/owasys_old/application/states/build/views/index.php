<?php
declare(strict_types=1);

return [
    'state' => 'build',
    'title' => 'Build & Validate',
    'badge' => 'Application creator',
    'summary' => 'Generate, validate and export OPUS application skeletons through the OWASYS creator pipeline.',
    'sections' => ['Creation request', 'Scaffold plan', 'Dry-run', 'Write', 'Validation', 'Manifest', 'Export'],
    'cards' => [
        [
            'title' => 'ApplicationCreator',
            'body' => 'OWASYS runs request validation, scaffold planning, mandatory dry-run, optional write, validation and creation manifest.',
            'items' => ['Opus/Owasys/ApplicationCreator.php', 'tools/owasys_create_application.php', 'default mode: dry-run'],
        ],
        [
            'title' => 'Scaffold plan',
            'body' => 'Generation remains split into a plan phase before write operations, so directories and files can be validated first.',
            'items' => ['Opus/Owasys/ScaffoldPlanBuilder.php', 'config/scaffold-plan.schema.json', 'forbidden roots: public, src, resources'],
        ],
        [
            'title' => 'State-first output',
            'body' => 'Generated sites use application/states/<state> as the canonical application node tree.',
            'items' => ['application/default', 'application/states/<state>', 'config/application.fsm.json'],
        ],
    ],
    'contracts' => ['OWASYS_APPLICATION_CREATION_RESULT_V1', 'OWASYS_CREATE_APPLICATION_CONTRACT_V1', 'OWASYS_SCAFFOLD_PLAN_SCHEMA_V1', 'OWASYS_EXPORT_POLICY_V1', 'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL'],
    'actions' => ['Validate application creation request', 'Generate scaffold plan', 'Run mandatory dry-run', 'Write standard OPUS site tree only with --write', 'Run OPUS site validation', 'Create clean creation manifest'],
];