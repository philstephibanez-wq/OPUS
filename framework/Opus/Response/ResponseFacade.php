<?php

declare(strict_types=1);

namespace Opus\Response;

use ASAP\Http\Response as HttpResponse;

/*
 * OPUS_REFBOOK:
 *   domain: RESPONSE
 *   role: Class ResponseFacade belongs to the RESPONSE Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the RESPONSE domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - response-overview
 *   diagrams:
 *     - response-runtime
 * END_OPUS_REFBOOK
 */
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
final class ResponseFacade
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
