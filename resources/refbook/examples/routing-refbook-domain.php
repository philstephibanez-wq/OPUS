<?php
declare(strict_types=1);

/*
 * Opus RefBook example: Routing RefBook domain marker.
 */

/*
 * OPUS_REFBOOK:
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
 * END_OPUS_REFBOOK
 */
