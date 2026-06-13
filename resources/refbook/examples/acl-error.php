<?php
declare(strict_types=1);

/*
 * Opus RefBook example: ACL error.
 *
 * Purpose:
 *   Demonstrate the expected behavior when ACL denies access.
 */

use ASAP\Acl\AccessContext;
use ASAP\Acl\AccessControlException;

$context = new AccessContext(
    role: 'guest',
    resource: 'admin-panel',
    privilege: 'read',
);

try {
    throw AccessControlException::because(
        'OPUS_ACCESS_DENIED',
        $context->role() . ':' . $context->resource() . ':' . $context->privilege()
    );
} catch (AccessControlException $exception) {
    // Expected explicit boundary failure.
    // Do not silently route to another page or downgrade privileges.
    error_log($exception->getMessage());
}
