<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class DiagnosticsController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/diagnostics';
    }

    public function title(): string
    {
        return 'Diagnostics';
    }

    public function group(): string
    {
        return 'Exploitation';
    }

    public function isExpert(): bool
    {
        return false;
    }

    public function render(array $context = []): string
    {
        return $this->shell($this->title(), $this->moduleCard('Santé système, contrôles et diagnostics non sensibles.', array (
  0 => 
  array (
    'label' => 'Health OPS existant',
    'href' => '/opus-lstsar-manager/health',
  ),
)), $context);
    }
}