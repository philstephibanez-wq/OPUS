<?php

declare(strict_types=1);

namespace Opus\Rest;

use ASAP\Http\Response;

/*
 * OPUS_REFBOOK:
 *   domain: REST
 *   role: Class Rest belongs to the REST Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the REST domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - rest-overview
 *   diagrams:
 *     - rest-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY-ALIGNED REST HELPER
 *
 * Role:
 *   Preserve the original Opus `REST\Rest` domain.
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
