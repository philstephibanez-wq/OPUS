<?php

declare(strict_types=1);

namespace ASAP\Controller;

use ASAP\Application\ApplicationPaths;
use ASAP\Http\Response;
use ASAP\Renderer\ViewModel;
use ASAP\Template\TemplateRendererInterface;

/**
 * PUBLIC BASE CONTROLLER
 *
 * Role:
 *   Provide small response helpers for ASAP controllers.
 *
 * Responsibility:
 *   Build explicit controller results without rendering side effects.
 *
 * Contract:
 *   Controller orchestrates only. HTML rendering is delegated to Renderer.
 *
 * Since:
 *   P112D4B
 */
abstract class AbstractController implements ControllerInterface
{
    public function __construct(
        protected readonly ApplicationPaths $paths,
        protected readonly TemplateRendererInterface $templateRenderer
    ) {
    }

    /**
     * PUBLIC HELPER
     *
     * @param string $template Template name.
     * @param array<string,mixed> $data View data.
     *
     * @return ViewModel Deferred HTML representation.
     */
    protected function view(string $template, array $data = []): ViewModel
    {
        return new ViewModel($template, $data);
    }

    /**
     * PUBLIC HELPER
     *
     * @param array<string,mixed> $data JSON-serializable data.
     *
     * @return Response JSON response.
     */
    protected function json(array $data, int $status = 200): Response
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new Response($json, $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }
}
