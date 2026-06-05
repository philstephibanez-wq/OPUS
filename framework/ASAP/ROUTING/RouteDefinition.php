<?php

declare(strict_types=1);

namespace ASAP\Routing;

use ASAP\Contract\ContractException;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Declare one route contract.
 *
 * Responsibility:
 *   Hold route name, pattern, controller class, action, defaults and optional
 *   runtime metadata used by the compiler/manifest pipeline.
 *
 * Contract:
 *   Route target must be explicit. No controller name guessing.
 *
 * Since:
 *   P112D1
 *
 * Extended:
 *   P112Q1 adds methods/host/locale/format/ACL/FSM/source metadata while
 *   preserving the original constructor arguments.
 */
final class RouteDefinition
{
    /**
     * @param array<string,string> $defaults Route defaults.
     * @param string[] $methods HTTP methods.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly string $controllerClass,
        public readonly string $action,
        public readonly array $defaults = [],
        public readonly array $methods = ['GET'],
        public readonly ?string $host = null,
        public readonly ?string $locale = null,
        public readonly string $format = 'html',
        public readonly ?string $acl = null,
        public readonly ?string $fsmGuard = null,
        public readonly int $priority = 0,
        public readonly string $source = 'explicit'
    ) {
        foreach ([$this->name, $this->path, $this->controllerClass, $this->action] as $value) {
            if (trim($value) === '') {
                throw ContractException::because('ASAP_ROUTE_DEFINITION_INVALID');
            }
        }

        if (!str_starts_with($this->path, '/')) {
            throw ContractException::because('ASAP_ROUTE_PATH_INVALID', $this->path);
        }

        if ($this->methods === []) {
            throw ContractException::because('ASAP_ROUTE_METHODS_EMPTY', $this->name);
        }

        foreach ($this->normalizedMethods() as $method) {
            if ($method === '') {
                throw ContractException::because('ASAP_ROUTE_METHOD_INVALID', $this->name);
            }
        }

        if (trim($this->format) === '') {
            throw ContractException::because('ASAP_ROUTE_FORMAT_EMPTY', $this->name);
        }
    }

    /** @return string[] */
    public function normalizedMethods(): array
    {
        $methods = array_values(array_unique(array_map(
            static fn (string $method): string => strtoupper(trim($method)),
            $this->methods
        )));
        sort($methods);

        return $methods;
    }

    /** @return array<string,mixed> */
    public function toManifestRow(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'methods' => $this->normalizedMethods(),
            'controller' => $this->controllerClass,
            'action' => $this->action,
            'defaults' => $this->defaults,
            'host' => $this->host,
            'locale' => $this->locale,
            'format' => $this->format,
            'acl' => $this->acl,
            'fsm_guard' => $this->fsmGuard,
            'priority' => $this->priority,
            'source' => $this->source,
        ];
    }
}
