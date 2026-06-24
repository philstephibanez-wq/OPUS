<?php
declare(strict_types=1);

namespace Opus\Runtime;

use Opus\Routing\Router;

use Opus\View\View;

use Opus\I18n\I18n;

use Opus\FSM\Fsm;
use Opus\Security\Acl;
use Opus\Application\ApplicationRegistry;
use Opus\Application\ApplicationDefinition;
use Opus\Http\Response;

use Opus\Http\Request;

/**
 * Modern OPUS runtime kernel responsible for application resolution and request dispatch.
 *
 * Coordinates application registry, I18N, routing, ACL, FSM and URL generation for integrated OPUS applications.
 */
final class Kernel
{
    private string $rootDir;
    private ApplicationRegistry $applications;
    private I18n $i18n;
    private Router $router;
    private ?Request $request = null;

    public function __construct(string $rootDir)
    {
        $this->rootDir = rtrim($rootDir, '/\\');
        $this->applications = new ApplicationRegistry($this->rootDir);
        $this->i18n = new I18n();
        $view = new View($this, $this->i18n);
        $this->router = new Router($this, $view, new Acl(), new Fsm());
    }

    public function handle(Request $request): Response
    {
        $this->request = $request;
        [$application, $segments] = $this->applications->resolve($request);
        return $this->router->dispatch($application, $segments, $request);
    }

    public function rootDir(): string
    {
        return $this->rootDir;
    }

    public function getApplication(string $slug): ApplicationDefinition
    {
        return $this->applications->get($slug);
    }

    public function applicationUrl(string $applicationSlug, string $route = '', ?string $lang = null): string
    {
        $application = $this->applications->get($applicationSlug);
        $lang = $lang !== null && $application->hasLanguage($lang) ? $lang : $application->defaultLang;
        $base = $this->request ? $this->request->basePath : '';
        $parts = [$base];
        if ($applicationSlug !== 'logandplay') {
            $parts[] = $applicationSlug;
        }
        $parts[] = $lang;
        if ($route !== '') {
            $parts[] = trim($route, '/');
        }
        return $this->joinUrlParts($parts);
    }

    public function pageUrl(ApplicationDefinition $application, string $lang, string $pageId): string
    {
        $routes = $application->routes();
        $langRoutes = (array)($routes[$lang] ?? []);
        $route = '';
        foreach ($langRoutes as $candidate => $candidatePageId) {
            if ($candidatePageId === $pageId) {
                $route = (string)$candidate;
                break;
            }
        }
        return $this->applicationUrl($application->slug, $route, $lang);
    }

    public function apiUrl(string $applicationSlug, string $endpoint): string
    {
        $base = $this->request ? $this->request->basePath : '';
        $parts = [$base];
        if ($applicationSlug !== 'logandplay') {
            $parts[] = $applicationSlug;
        }
        $parts[] = 'api';
        $parts[] = trim($endpoint, '/');
        return $this->joinUrlParts($parts);
    }

    public function assetUrl(ApplicationDefinition $application, string $asset): string
    {
        $base = $this->request ? $this->request->basePath : '';
        return $this->joinUrlParts([$base, 'sites', $application->slug, 'www', trim($asset, '/')]);
    }

    /** @param list<string> $parts */
    private function joinUrlParts(array $parts): string
    {
        $clean = [];
        foreach ($parts as $part) {
            $part = trim($part, '/');
            if ($part !== '') {
                $clean[] = $part;
            }
        }
        return '/' . implode('/', array_map(static fn($p) => str_replace('%2F', '/', rawurlencode($p)), $clean));
    }
}
