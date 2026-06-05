<?php
declare(strict_types=1);
namespace ASAP\ROUTER;
final class Route
{
    public function __construct(public readonly string $name, public readonly string $path, public readonly string $controller, public readonly string $action) { if (trim($this->name) === '' || $this->path === '' || $this->path[0] !== '/') { throw new \InvalidArgumentException('ASAP_ROUTE_INVALID'); } }
}
