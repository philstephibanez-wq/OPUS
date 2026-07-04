<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class ModelsManagerController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/models';
    }

    public function title(): string
    {
        return 'Models';
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
        return $this->shell($this->title(), $this->moduleCard('Modèles, schémas, objets typés et diagnostics.', array (
  0 => 
  array (
    'label' => 'Route OPS Models existante',
    'href' => '/opus-lstsar-manager/models',
  ),
)), $context);
    }
}