<?php
declare(strict_types=1);

namespace Opus\Api;

use Opus\Http\Response;

/**
 * Creates the canonical OPUS JSON error envelope.
 */
final class ApiErrorResponseFactory
{
    /** @param array<string,mixed> $details */
    public function error(string $code, string $message, int $status, array $details = []): Response
    {
        return Response::json([
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }
}
