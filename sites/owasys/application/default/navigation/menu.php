<?php
declare(strict_types=1);

/**
 * Presentation metadata only.
 *
 * This file is not a route registry and not an authorization source.
 * The visible menu is produced from currently available FSM transitions after
 * ACL/RBAC authorization and guard evaluation.
 */
return [
    'open_home' => ['label_key' => 'menu.home', 'order' => 10],
    'open_registry' => ['label_key' => 'menu.applications', 'order' => 20],
    'open_structure' => ['label_key' => 'menu.structure', 'order' => 30],
    'open_data' => ['label_key' => 'menu.data', 'order' => 40],
    'open_workflows' => ['label_key' => 'menu.workflows', 'order' => 50],
    'open_security' => ['label_key' => 'menu.security', 'order' => 60],
    'open_source' => ['label_key' => 'menu.source', 'order' => 70],
    'open_build' => ['label_key' => 'menu.build', 'order' => 80],
];
