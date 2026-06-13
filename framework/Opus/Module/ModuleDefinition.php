<?php

declare(strict_types=1);

namespace Opus\Module;

use ASAP\Contract\ContractException;

/*
 * OPUS_REFBOOK:
 *   domain: MODULE
 *   role: Class ModuleDefinition belongs to the MODULE Opus framework domain.
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
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Declare one Opus module.
 *
 * Contract:
 *   Module definition is data only. It does not route, render or authorize.
 *
 * Since:
 *   P112D4A
 */
final class ModuleDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly bool $enabled,
        public readonly string $defaultAction
    ) {
        if (trim($this->name) === '') {
            throw ContractException::because('OPUS_MODULE_NAME_EMPTY');
        }

        if (trim($this->defaultAction) === '') {
            throw ContractException::because('OPUS_MODULE_DEFAULT_ACTION_EMPTY', $this->name);
        }
    }
}
