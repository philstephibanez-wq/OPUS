<?php

declare(strict_types=1);

namespace ASAP\CONTROLLER;

use ASAP\Application\ApplicationPaths;
use ASAP\Http\Request;
use ASAP\Http\Response;
use ASAP\Renderer\HtmlRenderer;
use ASAP\Renderer\ViewModel;
use ASAP\Routing\RouteMatch;
use ASAP\Template\TemplateRendererInterface;
use ASAP\VIEW\Html;
use ReflectionClass;
use ReflectionNamedType;

/**
 * PUBLIC LEGACY-ALIGNED DISPATCHER
 *
 * Role:
 *   Instantiate controllers and execute matched actions.
 *
 * Responsibility:
 *   Convert controller results into HTTP responses through official renderers.
 *
 * Legacy alignment:
 *   Lives in `ASAP\CONTROLLER`, matching the original framework domain.
 *
 * Contract:
 *   Dispatcher does not route, does not authorize, does not read content and
 *   does not render templates directly.
 *
 * Since:
 *   P112D4C
 */
final class ControllerDispatcher
{
    public function __construct(
        private readonly ApplicationPaths $paths,
        private readonly TemplateRendererInterface $templateRenderer,
        private readonly HtmlRenderer $htmlRenderer
    ) {
    }

    public function dispatch(Request $request, RouteMatch $match): Response
    {
        $controller = $this->createController($match->controllerClass);

        if (!is_callable([$controller, $match->action])) {
            throw ControllerException::because('ASAP_CONTROLLER_ACTION_MISSING', $match->controllerClass . '::' . $match->action);
        }

        $result = $controller->{$match->action}($request, $match->params);

        if ($result instanceof Response) {
            return $result;
        }

        if ($result instanceof Html) {
            return $result->toResponse($this->templateRenderer);
        }

        if ($result instanceof ViewModel) {
            return $this->htmlRenderer->render($result);
        }

        throw ControllerException::because('ASAP_CONTROLLER_RESULT_INVALID', $match->controllerClass . '::' . $match->action);
    }

    private function createController(string $controllerClass): object
    {
        if (!class_exists($controllerClass)) {
            throw ControllerException::because('ASAP_CONTROLLER_CLASS_MISSING', $controllerClass);
        }

        $reflection = new ReflectionClass($controllerClass);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return $reflection->newInstance();
        }

        $parameters = $constructor->getParameters();

        if (count($parameters) !== 2) {
            throw ControllerException::because('ASAP_CONTROLLER_CONSTRUCTOR_CONTRACT_INVALID', $controllerClass);
        }

        $firstType = $parameters[0]->getType();
        $secondType = $parameters[1]->getType();

        if (!$firstType instanceof ReflectionNamedType || $firstType->getName() !== ApplicationPaths::class) {
            throw ControllerException::because('ASAP_CONTROLLER_CONSTRUCTOR_PATHS_INVALID', $controllerClass);
        }

        if (!$secondType instanceof ReflectionNamedType || $secondType->getName() !== TemplateRendererInterface::class) {
            throw ControllerException::because('ASAP_CONTROLLER_CONSTRUCTOR_RENDERER_INVALID', $controllerClass);
        }

        return $reflection->newInstance($this->paths, $this->templateRenderer);
    }
}
