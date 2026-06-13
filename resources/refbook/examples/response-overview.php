<?php
declare(strict_types=1);

/*
 * Opus RefBook example: HTTP response overview.
 *
 * Purpose:
 *   Build a typed HTTP response without rendering logic inside controllers.
 */

use ASAP\Http\Response;

$response = new Response(
    status: 200,
    headers: ['Content-Type' => 'text/plain; charset=UTF-8'],
    body: 'OK'
);

$response->send();
