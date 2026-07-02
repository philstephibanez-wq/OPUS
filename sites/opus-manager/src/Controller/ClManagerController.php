<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class ClManagerController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/cl';
    }

    public function title(): string
    {
        return 'CL';
    }

    public function group(): string
    {
        return 'Moteurs';
    }

    public function isExpert(): bool
    {
        return true;
    }

    public function render(array $context = []): string
    {
        return $this->shell($this->title(), $this->moduleCard('CL et orchestration des couches OPUS associées.', array (
  0 => 
  array (
    'label' => 'Route OPS CL existante',
    'href' => '/opus-lstsar-manager/cl',
  ),
)), $context);
    }
}