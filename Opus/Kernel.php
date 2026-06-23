<?php
declare(strict_types=1);

namespace Opus;

use Opus\Http\Response;

use Opus\Http\Request;

final class Kernel
{
    private string $rootDir;
    private PackageRepository $packages;
    private I18n $i18n;
    private Router $router;
    private ?Request $request = null;

    public function __construct(string $rootDir)
    {
        $this->rootDir = rtrim($rootDir, '/\\');
        $this->packages = new PackageRepository($this->rootDir);
        $this->i18n = new I18n();
        $view = new View($this, $this->i18n);
        $this->router = new Router($this, $view, new Acl(), new Fsm());
    }

    public function handle(Request $request): Response
    {
        $this->request = $request;
        [$package, $segments] = $this->packages->resolve($request);
        return $this->router->dispatch($package, $segments, $request);
    }

    public function rootDir(): string
    {
        return $this->rootDir;
    }

    public function getPackage(string $slug): Package
    {
        return $this->packages->get($slug);
    }

    public function packageUrl(string $packageSlug, string $route = '', ?string $lang = null): string
    {
        $package = $this->packages->get($packageSlug);
        $lang = $lang !== null && $package->hasLanguage($lang) ? $lang : $package->defaultLang;
        $base = $this->request ? $this->request->basePath : '';
        $parts = [$base];
        if ($packageSlug !== 'logandplay') {
            $parts[] = $packageSlug;
        }
        $parts[] = $lang;
        if ($route !== '') {
            $parts[] = trim($route, '/');
        }
        return $this->joinUrlParts($parts);
    }

    public function pageUrl(Package $package, string $lang, string $pageId): string
    {
        $routes = $package->routes();
        $langRoutes = (array)($routes[$lang] ?? []);
        $route = '';
        foreach ($langRoutes as $candidate => $candidatePageId) {
            if ($candidatePageId === $pageId) {
                $route = (string)$candidate;
                break;
            }
        }
        return $this->packageUrl($package->slug, $route, $lang);
    }

    public function apiUrl(string $packageSlug, string $endpoint): string
    {
        $base = $this->request ? $this->request->basePath : '';
        $parts = [$base];
        if ($packageSlug !== 'logandplay') {
            $parts[] = $packageSlug;
        }
        $parts[] = 'api';
        $parts[] = trim($endpoint, '/');
        return $this->joinUrlParts($parts);
    }

    public function assetUrl(Package $package, string $asset): string
    {
        $base = $this->request ? $this->request->basePath : '';
        return $this->joinUrlParts([$base, 'sites', $package->slug, 'www', trim($asset, '/')]);
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
