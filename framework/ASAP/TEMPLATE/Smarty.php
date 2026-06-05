<?php

declare(strict_types=1);

namespace ASAP\TEMPLATE;

/**
 * PUBLIC LEGACY-ALIGNED SMARTY ADAPTER PLACEHOLDER
 *
 * Role:
 *   Preserve the original ASAP `TEMPLATE\Smarty` adapter name.
 *
 * Responsibility:
 *   Make the Smarty dependency boundary explicit.
 *
 * Contract:
 *   This adapter fails clearly until a licensed/installed Smarty runtime is
 *   wired contractually. No silent Twig substitution.
 *
 * Since:
 *   P112D4C
 */
final class Smarty implements Adapter
{
    public function render(string $template, array $data = []): string
    {
        throw TemplateException::because('ASAP_TEMPLATE_SMARTY_RUNTIME_NOT_CONFIGURED', $template);
    }
}
