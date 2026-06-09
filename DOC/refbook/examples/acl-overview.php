<?php
declare(strict_types=1);

/*
 * ASAP RefBook example: ACL overview.
 *
 * Purpose:
 *   Show the intended ACL vocabulary used by ASAP route/security metadata.
 *
 * Contract:
 *   ACL decisions are explicit. A route or controller must never silently
 *   continue when the access decision is denied.
 */

use ASAP\Acl\Acl;
use ASAP\Acl\AccessContext;
use ASAP\Acl\AccessControl;
use ASAP\Acl\AccessDecision;
use ASAP\Acl\AccessRule;
use ASAP\Acl\ResourceDefinition;
use ASAP\Acl\RoleDefinition;

// 1. Declare roles/resources/privileges in configuration or bootstrap code.
$acl = new Acl(
    roles: [
        new RoleDefinition('admin'),
        new RoleDefinition('reader'),
    ],
    resources: [
        new ResourceDefinition('refbook'),
    ],
    rules: [
        AccessRule::allow('admin', 'refbook', 'read'),
        AccessRule::allow('reader', 'refbook', 'read'),
    ],
);

// 2. Build a request-scoped access context.
$context = new AccessContext(
    role: 'reader',
    resource: 'refbook',
    privilege: 'read',
);

// 3. Ask the access controller for an explicit decision.
$control = new AccessControl($acl);
$decision = $control->canView($context);

if (!$decision instanceof AccessDecision || !$decision->allowed()) {
    throw new RuntimeException('ASAP_ACL_DECISION_DENIED');
}
