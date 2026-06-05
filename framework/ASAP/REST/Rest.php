<?php

declare(strict_types=1);

namespace ASAP\REST;

use ASAP\Http\Response;

/**
 * PUBLIC LEGACY-ALIGNED REST HELPER
 *
 * Role:
 *   Preserve the original ASAP `REST\Rest` domain.
 *
 * Responsibility:
 *   Build explicit JSON responses.
 *
 * Contract:
 *   REST helper represents prepared data only. It does not call services.
 *
 * Since:
 *   P112D4C
 */
final class Rest
{
    /**
     * @param array<string,mixed> $data JSON data.
     */
    public function json(array $data, int $status = 200): Response
    {
        return new Response(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }
}
