<?php
declare(strict_types=1);

return [
    'state' => 'structure',
    'title_key' => 'state.structure.title',
    'badge_key' => 'state.structure.title',
    'summary_key' => 'state.default.summary',
    'contracts' => [
        'OWASYS_APPLICATION_INSPECTION_V1',
        'OPUS_ROUTE_REGISTRY_V1',
        'OPUS_APPLICATION_FSM_V1',
        'OPUS_SITE_APPLICATION_TREE_V1_ETERNAL',
    ],
    'action_keys' => [
        'inspection.action.validate',
        'inspection.action.open_registry',
    ],
];
