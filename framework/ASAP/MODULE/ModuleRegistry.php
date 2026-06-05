<?php

declare(strict_types=1);

namespace ASAP\Module;

use ASAP\Contract\ContractException;

/**
 * PUBLIC REGISTRY
 *
 * Role:
 *   Store explicit module definitions.
 *
 * Responsibility:
 *   Resolve enabled modules by name.
 *
 * Contract:
 *   Missing or disabled modules fail explicitly.
 *
 * Since:
 *   P112D4A
 */
final class ModuleRegistry
{
    /** @var array<string,ModuleDefinition> */
    private array $modules = [];

    /**
     * @param ModuleDefinition[] $modules
     */
    public function __construct(array $modules)
    {
        foreach ($modules as $module) {
            $this->modules[$module->name] = $module;
        }
    }

    public function getEnabled(string $name): ModuleDefinition
    {
        if (!array_key_exists($name, $this->modules)) {
            throw ContractException::because('ASAP_MODULE_UNKNOWN', $name);
        }

        $module = $this->modules[$name];

        if (!$module->enabled) {
            throw ContractException::because('ASAP_MODULE_DISABLED', $name);
        }

        return $module;
    }
}
