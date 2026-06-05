<?php

declare(strict_types=1);

namespace ASAP\TEMPLATE;

/**
 * PUBLIC LEGACY-ALIGNED TEMPLATE ADAPTER CONTRACT
 *
 * Role:
 *   Preserve the original ASAP `TEMPLATE\Adapter` concept.
 *
 * Responsibility:
 *   Render one template with prepared data.
 *
 * Contract:
 *   Template adapter represents only. It does not route, fetch services or
 *   decide permissions.
 *
 * Since:
 *   P112D4C
 *
 * Legacy compatibility:
 *   P112P1 restores loadTemplate().
 */
interface Adapter
{
    public function loadTemplate(string $template): string;

    /**
     * @param array<string,mixed> $data Prepared template data.
     */
    public function render(string $template, array $data = []): string;
}
