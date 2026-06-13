<?php
declare(strict_types=1);

/*
 * Opus RefBook example: ACL RefBook domain marker.
 *
 * Purpose:
 *   Show the source annotation pattern used to classify ACL symbols.
 */

/*
 * OPUS_REFBOOK:
 *   domain: ACL
 *   role: Class AccessControl belongs to the ACL framework domain.
 *   contract:
 *     - decides authorization explicitly
 *     - never corrects missing roles or privileges silently
 *     - returns a typed access decision or raises an explicit contract error
 *   examples:
 *     - acl-overview
 *     - acl-condition
 *     - acl-error
 *   diagrams:
 *     - acl-runtime
 * END_OPUS_REFBOOK
 */
