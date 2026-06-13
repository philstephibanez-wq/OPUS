<?php

declare(strict_types=1);

namespace Opus\Renderer;

use ASAP\Http\Response;
use JsonException;

/*
 * OPUS_REFBOOK:
 *   domain: RENDERER
 *   role: Class JsonRenderer belongs to the RENDERER Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the RENDERER domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - renderer-overview
 *   diagrams:
 *     - renderer-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC RENDERER
 *
 * Role:
 *   Render JSON responses from prepared data.
 *
 * Responsibility:
 *   Encode data explicitly and return an HTTP response.
 *
 * Contract:
 *   JSON encoding failures are explicit. No empty JSON fallback.
 *
 * Since:
 *   P112D4B
 */
final class JsonRenderer
{
    /**
     * @param array<string,mixed> $data JSON-serializable data.
     */
    public function render(array $data, int $status = 200): Response
    {
        try {
            $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw RenderException::because('OPUS_JSON_RENDER_FAILED', $exception->getMessage());
        }

        return new Response($body, $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }
}
