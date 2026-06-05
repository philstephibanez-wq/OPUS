<?php

declare(strict_types=1);

namespace ASAP\Renderer;

use ASAP\Http\Response;
use JsonException;

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
            throw RenderException::because('ASAP_JSON_RENDER_FAILED', $exception->getMessage());
        }

        return new Response($body, $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }
}
