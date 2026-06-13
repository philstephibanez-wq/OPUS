<?php
declare(strict_types=1);

/*
 * Opus RefBook example: response send.
 *
 * Purpose:
 *   The response object is the only object that emits headers/body.
 */

use ASAP\Http\Response;

$response = Response::text('Opus response ready', 200);
$response->send();
