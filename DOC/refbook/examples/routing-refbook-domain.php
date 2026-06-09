<?php
declare(strict_types=1);

/*
 * ASAP RefBook example: Routing RefBook domain marker.
 */

/*
 * ASAP_REFBOOK:
 *   domain: ROUTING
 *   role: Router and route definitions resolve requests to controller actions.
 *   contract:
 *     - no implicit route fallback
 *     - method mismatch is explicit
 *     - route match returns typed controller/action metadata
 *   examples:
 *     - routing-overview
 *     - attribute-routing
 *     - secure-dispatch-gate
 *   diagrams:
 *     - routing-runtime
 *     - secure-dispatch-runtime
 * END_ASAP_REFBOOK
 */
