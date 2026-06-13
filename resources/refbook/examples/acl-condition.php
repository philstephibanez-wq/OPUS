<?php
declare(strict_types=1);

/*
 * Opus RefBook example: ACL condition.
 *
 * Purpose:
 *   Show how a rule can stay explicit while accepting a contextual condition.
 */

use ASAP\Acl\AccessContext;
use ASAP\Acl\AccessRule;

$rule = AccessRule::allow(
    role: 'admin',
    resource: 'refbook',
    privilege: 'read',
    condition: static function (AccessContext $context): bool {
        return $context->resource() === 'refbook'
            && $context->privilege() === 'read';
    }
);

$context = new AccessContext(
    role: 'admin',
    resource: 'refbook',
    privilege: 'read',
);

if (!$rule->allows($context)) {
    throw new RuntimeException('OPUS_ACL_CONDITION_DENIED');
}
