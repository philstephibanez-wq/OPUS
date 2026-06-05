<?php

declare(strict_types=1);

namespace ASAP\CONTROLLER;

use ASAP\Application\ApplicationPaths;
use ASAP\Http\Response;
use ASAP\Template\TemplateRendererInterface;
use ASAP\VIEW\Html;

/**
 * PUBLIC LEGACY-ALIGNED BASE CONTROLLER
 *
 * Role:
 *   Preserve the original ASAP `CONTROLLER\Controller` concept.
 *
 * Responsibility:
 *   Provide controller helpers while keeping rendering delegated to VIEW/Renderer.
 *
 * Contract:
 *   Controller coordinates. Services produce data. VIEW/Renderer represent.
 *
 * Since:
 *   P112D4C
 */
class Controller implements ControllerInterface
{
    public function __construct(
        protected readonly ?ApplicationPaths $paths = null,
        protected readonly ?TemplateRendererInterface $templateRenderer = null
    ) {
    }

    /**
     * @param array<string,mixed> $data View data.
     */
    protected function view(string $template, array $data = [], int $status = 200): Html
    {
        return new Html($template, $data, $status);
    }

    /**
     * @param array<string,mixed> $data JSON data.
     */
    protected function json(array $data, int $status = 200): Response
    {
        return new Response(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }
}
