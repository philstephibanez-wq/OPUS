<?php
declare(strict_types=1);

/*
 * ASAP RefBook example: response send.
 *
 * Purpose:
 *   The response object is the only object that emits headers/body.
 */

use ASAP\Http\Response;

$response = Response::text('ASAP response ready', 200);
$response->send();
