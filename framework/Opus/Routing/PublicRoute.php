<?php

declare(strict_types=1);

namespace Opus\Routing;

use InvalidArgumentException;
use Opus\Http\PublicRequest;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Declare a public route for the OPUS public MVC smoke pipeline.
 *
 * Responsibility:
 *   Bind method, path, profile and controller class without executing anything.
 *
 * Contract:
 *   The route remains a declaration. Control-plane authorization must happen
 *   before the controller is invoked.
 */
final class PublicRoute
{
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly string $profile,
        private readonly string $controllerClass
    ) {
        if ($this->method === '' || $this->path === '' || $this->profile === '' || $this->controllerClass === '') {
            throw new InvalidArgumentException('OPUS_PUBLIC_ROUTE_DECLARATION_INVALID');
        }
    }

    public static function get(string $path, string $profile, string $controllerClass): self
    {
        return new self('GET', $path, $profile, $controllerClass);
    }

    public function matches(PublicRequest $request): bool
    {
        return strtoupper($this->method) === $request->method() && $this->path === $request->path();
    }

    public function profile(): string
    {
        return $this->profile;
    }

    public function controllerClass(): string
    {
        return $this->controllerClass;
    }
}
