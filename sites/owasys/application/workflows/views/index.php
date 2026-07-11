<?php
declare(strict_types=1);

/**
 * OWASYS workflows view-model.
 * Data only: workflow generation remains contract-driven.
 */
return [
    'title' => 'Workflows',
    'badge' => 'FSM',
    'summary' => 'Configuration of generation workflows, FSM states, transitions, guards and actions.',
    'sections' => [
        'States',
        'Transitions',
        'Guards',
        'Events',
        'Jobs',
    ],
    'cards' => [
        [
            'title' => 'Generation pipeline',
            'body' => 'OWASYS formalizes each application generation step as explicit workflow data.',
            'items' => [
                'load-request',
                'secure-request',
                'transform-blueprint',
                'store-registry',
                'validate-site',
                'export',
            ],
        ],
        [
            'title' => 'Explicit failure policy',
            'body' => 'Workflow errors must stop the pipeline and expose the failing contract.',
            'items' => [
                'no silent fallback',
                'guards before mutation',
                'validation before export',
            ],
        ],
    ],
    'contracts' => [
        'OWASYS_GENERATION_PIPELINE_V1',
        'OPUS_FSM_REGISTRY_V1',
    ],
    'actions' => [
        'Add state',
        'Add transition',
        'Attach validation job',
        'Document guard failure output',
    ],
];
