<?php

declare(strict_types=1);

namespace Opus\Runtime;

use Opus\Admin\AdminDashboardResponse;
use Opus\Http\PublicResponse;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Emit a native OPUS HTTP response produced by the runtime kernel.
 *
 * Responsibility:
 *   Apply status, headers and body to the active PHP HTTP request only at the
 *   runtime boundary.
 *
 * Contract:
 *   This class does not authorize, route, render, transform or inspect payloads.
 *   The response object is already the source of truth.
 */
final class NativeHttpEmitter
{
    public function emit(AdminDashboardResponse|PublicResponse $response): void
    {
        http_response_code($response->statusCode());

        foreach ($response->headers() as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $response->body();
    }
}
