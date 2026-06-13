<?php
declare(strict_types=1);

/*
 * Opus RefBook example: HTTP RefBook domain marker.
 */

/*
 * OPUS_REFBOOK:
 *   domain: HTTP
 *   role: Request and Response classes define the HTTP boundary.
 *   contract:
 *     - request data is normalized once
 *     - response emission is explicit
 *     - controllers do not echo directly
 *   examples:
 *     - http-overview
 *     - response-overview
 *     - response-html
 *     - response-json
 *     - response-send
 *   diagrams:
 *     - http-runtime
 * END_OPUS_REFBOOK
 */
