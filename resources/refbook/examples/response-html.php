<?php
declare(strict_types=1);

/*
 * Opus RefBook example: HTML response.
 *
 * Purpose:
 *   Show an HTML response boundary. Data is prepared before representation.
 */

use ASAP\Http\Response;

$html = '<!doctype html><html lang="fr"><body><h1>ASAP</h1></body></html>';

$response = new Response(
    status: 200,
    headers: ['Content-Type' => 'text/html; charset=UTF-8'],
    body: $html
);
