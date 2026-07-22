<?php
declare(strict_types=1);

namespace Opus\Api;

use Opus\Application\ApplicationDefinition;
use Opus\Fsm\Runtime\FsmRuntimeConfigLoader;
use Opus\Http\Request;
use Opus\Http\Response;
use Opus\Profiler\Profiler;
use Opus\Security\Access\ConfigAclPolicy;
use Opus\Security\Fsm\ConfigFsmGuard;
use Opus\Security\Sso\DevHeaderSsoAuthenticator;

/**
 * OPUS REST API dispatcher.
 *
 * This dispatcher is intentionally data-driven: Router only delegates to it, while
 * routes, ACL policies and SSO behavior are loaded from configuration contracts.
 */
final class ApiDispatcher implements ApiDispatcherInterface
{
    private ApiRouteRegistry $routes;
    private DevHeaderSsoAuthenticator $sso;
    private ConfigAclPolicy $acl;
    private ConfigFsmGuard $fsmGuard;
    private Profiler $profiler;
    private ApiErrorResponseFactory $errors;
    private string $projectRoot;

    public function __construct(ApiRouteRegistry $routes, DevHeaderSsoAuthenticator $sso, ConfigAclPolicy $acl, ConfigFsmGuard $fsmGuard, Profiler $profiler, ApiErrorResponseFactory $errors, string $projectRoot = '')
    {
        $this->routes = $routes;
        $this->sso = $sso;
        $this->acl = $acl;
        $this->fsmGuard = $fsmGuard;
        $this->profiler = $profiler;
        $this->errors = $errors;
        $this->projectRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR);
    }

    public static function fromProjectRoot(string $projectRoot, Profiler $profiler, FsmRuntimeConfigLoader $fsmRuntimeConfigLoader): self
    {
        $projectRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR);
        $configRoot = $projectRoot . DIRECTORY_SEPARATOR . 'config';

        return new self(
            ApiRouteRegistry::fromFile($configRoot . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'routes.json'),
            DevHeaderSsoAuthenticator::fromFile($configRoot . DIRECTORY_SEPARATOR . 'security' . DIRECTORY_SEPARATOR . 'sso.json'),
            ConfigAclPolicy::fromFile($configRoot . DIRECTORY_SEPARATOR . 'security' . DIRECTORY_SEPARATOR . 'acl.json'),
            new ConfigFsmGuard($fsmRuntimeConfigLoader),
            $profiler,
            new ApiErrorResponseFactory(),
            $projectRoot
        );
    }

    /** @param list<string> $segments */
    public function dispatch(ApplicationDefinition $application, array $segments, Request $request): Response
    {
        $path = trim(implode('/', $segments), '/');
        $this->profiler->event('api', 'dispatch.start', ['application' => $application->slug, 'method' => $request->method, 'path' => $path]);

        try {
            $route = $this->routes->match($request, $segments);
            if ($route === null) {
                $this->profiler->event('api', 'route.not_found', ['method' => $request->method, 'path' => $path]);

                return $this->errors->error('OPUS_API_ROUTE_NOT_FOUND', 'No OPUS API route matched the request.', 404, [
                    'method' => $request->method,
                    'path' => $path,
                ]);
            }

            $identity = $this->sso->authenticate($request);
            $this->profiler->event('security', 'sso.identity_resolved', $identity->toArray());

            $accessDecision = $this->acl->decide($route->aclPolicy, $identity);
            $this->profiler->event('security', 'acl.decision', $accessDecision->toArray() + ['route_id' => $route->id]);
            if (!$accessDecision->isGranted()) {
                $status = $identity->isAnonymous() ? 401 : 403;

                return $this->errors->error($identity->isAnonymous() ? 'OPUS_AUTH_REQUIRED' : 'OPUS_API_FORBIDDEN', $accessDecision->reason(), $status, [
                    'route_id' => $route->id,
                    'policy' => $route->aclPolicy,
                ]);
            }

            $fsmDecision = $this->fsmGuard->decide($route);
            $this->profiler->event('security', 'fsm_guard.decision', $fsmDecision->toArray() + ['route_id' => $route->id]);
            if (!$fsmDecision->isGranted()) {
                return $this->errors->error('OPUS_FSM_TRANSITION_DENIED', $fsmDecision->reason(), 409, [
                    'route_id' => $route->id,
                    'fsm_flow' => $route->fsmFlow,
                    'fsm_signal' => $route->fsmSignal,
                ]);
            }

            $endpoint = $this->makeEndpoint($route);
            $this->profiler->event('api', 'endpoint.handle', ['route_id' => $route->id, 'endpoint' => $route->endpointClass]);

            return $endpoint->handle($route, $application, $request, $identity, [
                'api_routes' => $this->routes->export(),
                'acl_policies' => $this->acl->export(),
                'project_root' => $this->projectRoot,
                'access_decision' => $accessDecision,
                'fsm_decision' => $fsmDecision,
            ]);
        } catch (\Throwable $exception) {
            $this->profiler->event('api', 'dispatch.failed', ['error' => $exception->getMessage()]);

            return $this->errors->error('OPUS_API_DISPATCH_FAILED', $exception->getMessage(), 500);
        }
    }

    private function makeEndpoint(ApiRoute $route): ApiEndpointInterface
    {
        if (!class_exists($route->endpointClass)) {
            throw new \RuntimeException('OPUS_API_ENDPOINT_CLASS_NOT_FOUND: ' . $route->endpointClass);
        }

        $endpoint = new $route->endpointClass();
        if (!$endpoint instanceof ApiEndpointInterface) {
            throw new \RuntimeException('OPUS_API_ENDPOINT_CONTRACT_INVALID: ' . $route->endpointClass);
        }

        return $endpoint;
    }
}
