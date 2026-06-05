<?php

declare(strict_types=1);

namespace ASAP\Module;

use ASAP\Contract\ContractException;

/**
 * PUBLIC VALUE OBJECT
 *
 * Role:
 *   Declare one ASAP module.
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
            throw ContractException::because('ASAP_MODULE_NAME_EMPTY');
        }

        if (trim($this->defaultAction) === '') {
            throw ContractException::because('ASAP_MODULE_DEFAULT_ACTION_EMPTY', $this->name);
        }
    }
}
