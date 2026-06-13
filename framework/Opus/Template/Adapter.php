<?php

declare(strict_types=1);

namespace Opus\Template;

/*
 * OPUS_REFBOOK:
 *   domain: TEMPLATE
 *   role: Interface Adapter belongs to the TEMPLATE Opus framework domain.
 *   contract:
 *     - keeps responsibility limited to the TEMPLATE domain
 *     - exposes explicit behavior for the RefBook extractor
 *     - must not rely on silent fallback behavior
 *   examples:
 *     - template-overview
 *   diagrams:
 *     - template-runtime
 * END_OPUS_REFBOOK
 */
/**
 * PUBLIC LEGACY-ALIGNED TEMPLATE ADAPTER CONTRACT
 *
 * Role:
 *   Preserve the original Opus `TEMPLATE\Adapter` concept.
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
