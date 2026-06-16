<?php

declare(strict_types=1);

namespace Opus\Security;

use Opus\Http\PublicResponse;

/**
 * PUBLIC SERVICE
 *
 * Role:
 *   Render the only default public blocked response allowed by OPUS.
 *
 * Responsibility:
 *   Hide all internal diagnostic details from public users.
 *
 * Contract:
 *   The public message must remain neutral and non-exploitable. Detailed FSM,
 *   ACL, route, token, class, path, stack or configuration diagnostics belong
 *   only to protected admin/dashboard/log contexts.
 */
final class PublicBlockedResponseRenderer
{
    public function render(): PublicResponse
    {
        return new PublicResponse(
            503,
            "Site temporairement bloqué.\nContactez le support.",
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }
}
