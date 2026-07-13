<?php
declare(strict_types=1);

return [
    'state' => 'structure',
    'title' => 'Structure',
    'badge' => 'Application tree',
    'summary' => 'Configuration of standard OPUS folders, routes, states, views, templates, themes, assets and languages.',
    'sections' => ['Routes and navigation', 'States', 'Templates, views, themes and assets', 'I18N language roots'],
    'cards' => [
        [
            'title' => 'Allowed OPUS roots',
            'body' => 'Generated applications follow the eternal OPUS site contract.',
            'items' => ['config/', 'application/default/', 'application/states/<state>/', 'www/', 'www/asset/'],
        ],
        [
            'title' => 'Forbidden legacy roots',
            'body' => 'OWASYS rejects non-standard roots and hidden wrapper layouts.',
            'items' => ['src/', 'public/', 'resources/', 'application/common/'],
        ],
    ],
    'contracts' => ['OPUS_ROUTE_REGISTRY_V1', 'OPUS_MENU_ROUTE_PROJECTION_V1', 'OPUS_I18N_LANGUAGE_REGISTRY_V1'],
    'actions' => ['Add route', 'Add state folder', 'Add language pack', 'Validate route-to-state consistency'],
];