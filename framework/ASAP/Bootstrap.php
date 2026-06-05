<?php

declare(strict_types=1);

namespace ASAP;

use ASAP\Application\Application;
use ASAP\Http\Request;
use ASAP\Http\Response as HttpResponse;

/**
 * PUBLIC LEGACY COMPATIBILITY SHIM
 *
 * Role:
 *   Restore the top-level bootstrap entry point without replacing the modern kernel.
 *
 * Contract:
 *   Requires an explicit callable or Application instance. No implicit app discovery.
 *
 * Since:
 *   P112O
 */
final class Bootstrap
{
    public function run(callable|Application $target, ?Request $request = null): mixed
    {
        if ($target instanceof Application) {
            $response = $target->run($request ?? Request::fromGlobals());

            if (!$response instanceof HttpResponse) {
                throw Exception::because('ASAP_BOOTSTRAP_RESPONSE_INVALID');
            }

            return $response;
        }

        return $target($this, $request);
    }
}
