<?php

declare(strict_types=1);

namespace ASAP;

use ASAP\Http\Response as HttpResponse;

/**
 * PUBLIC LEGACY COMPATIBILITY SHIM
 *
 * Role:
 *   Restore the top-level `ASAP\Response` facade.
 *
 * Contract:
 *   Delegates to the modern immutable Http\Response object.
 *
 * Since:
 *   P112O
 */
final class Response
{
    public static function html(string $body, int $status = 200): HttpResponse
    {
        return HttpResponse::html($body, $status);
    }

    /** @param mixed $data JSON-serializable data. */
    public static function json(mixed $data, int $status = 200): HttpResponse
    {
        return HttpResponse::json($data, $status);
    }
}
