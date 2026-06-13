<?php

declare(strict_types=1);

namespace Opus\Template;

/*
 * OPUS_REFBOOK:
 *   domain: TEMPLATE
 *   role: Class Smarty belongs to the TEMPLATE Opus framework domain.
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
 * PUBLIC LEGACY-ALIGNED SMARTY ADAPTER PLACEHOLDER
 *
 * Role:
 *   Preserve the original Opus `TEMPLATE\Smarty` adapter name.
 *
 * Responsibility:
 *   Make the Smarty dependency boundary explicit while preserving safe legacy
 *   assignment methods.
 *
 * Contract:
 *   This adapter fails clearly on rendering until a licensed/installed Smarty
 *   runtime is wired contractually. No silent Twig substitution.
 *
 * Since:
 *   P112D4C
 *
 * Legacy compatibility:
 *   P112P1 restores assign/assignAll/parse/loadTemplate.
 */
final class Smarty implements Adapter
{
    /** @var array<string,mixed> */
    private array $data = [];

    public function assign(string $name, mixed $value): void
    {
        if (trim($name) === '') {
            throw TemplateException::because('OPUS_TEMPLATE_ASSIGN_KEY_EMPTY');
        }

        $this->data[$name] = $value;
    }

    /** @param array<string,mixed> $data */
    public function assignAll(array $data): void
    {
        foreach ($data as $name => $value) {
            $this->assign((string) $name, $value);
        }
    }

    public function parse(string $template): string
    {
        return $this->render($template, $this->data);
    }

    public function loadTemplate(string $template): string
    {
        return $this->parse($template);
    }

    public function render(string $template, array $data = []): string
    {
        throw TemplateException::because('OPUS_TEMPLATE_SMARTY_RUNTIME_NOT_CONFIGURED', $template);
    }
}
