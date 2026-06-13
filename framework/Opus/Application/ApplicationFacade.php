<?php

declare(strict_types=1);

namespace Opus\Application;

use ASAP\Http\Request;
use ASAP\Http\Response;

/*
 * OPUS_REFBOOK:
 *   domain: APPLICATION
 *   role: Class ApplicationFacade belongs to the APPLICATION Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the APPLICATION domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - application-overview
 *   diagrams:
 *     - application-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY-COLLISION RECONCILIATION
 *
 * Role:
 *   Provide the legacy Opus APPLICATION facade without creating a Windows
 *   casing collision with the existing `Application` runtime class.
 *
 * Responsibility:
 *   Delegate to the canonical `ASAP\Application\Application` kernel.
 *
 * Contract:
 *   Facade only. No routing, rendering, security decision or fallback.
 *
 * Since:
 *   P112D4E
 */
final class ApplicationFacade
{
    private Application $application;

    public function __construct(ApplicationPaths $paths)
    {
        $this->application = new Application($paths);
    }

    public function run(Request $request): Response
    {
        return $this->application->run($request);
    }
}
