<?php

declare(strict_types=1);

namespace ASAP\TEMPLATE;

/**
 * PUBLIC LEGACY COMPATIBILITY SHIM
 *
 * Role:
 *   Restore the legacy `ASAP_TEMPLATE_X64` surface as an explicit compatibility
 *   adapter.
 *
 * Contract:
 *   Same contract as Smarty: assignment is stored, rendering fails explicitly
 *   until a real X64 runtime is contractually wired.
 *
 * Since:
 *   P112P1
 */
final class X64 implements Adapter
{
    /** @var array<string,mixed> */
    private array $data = [];

    public function assign(string $name, mixed $value): void
    {
        if (trim($name) === '') {
            throw TemplateException::because('ASAP_TEMPLATE_ASSIGN_KEY_EMPTY');
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
        throw TemplateException::because('ASAP_TEMPLATE_X64_RUNTIME_NOT_CONFIGURED', $template);
    }
}
