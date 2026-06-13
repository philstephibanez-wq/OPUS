<?php

declare(strict_types=1);

namespace Opus\Module;

use ASAP\Contract\ContractException;

/*
 * OPUS_REFBOOK:
 *   domain: MODULE
 *   role: Class ModuleRegistry belongs to the MODULE Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the MODULE domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - module-overview
 *   diagrams:
 *     - module-runtime
 * END_OPUS_REFBOOK
 */
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
            throw ContractException::because('OPUS_MODULE_UNKNOWN', $name);
        }

        $module = $this->modules[$name];

        if (!$module->enabled) {
            throw ContractException::because('OPUS_MODULE_DISABLED', $name);
        }

        return $module;
    }
}
