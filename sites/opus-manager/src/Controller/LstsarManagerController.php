<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class LstsarManagerController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/lstsar';
    }

    public function title(): string
    {
        return 'LSTSAR Manager';
    }

    public function group(): string
    {
        return 'Données';
    }

    public function isExpert(): bool
    {
        return true;
    }

    public function render(array $context = []): string
    {
        return $this->shell($this->title(), $this->moduleCard('Load / Secure / Transform / Store / Audit.', array (
  0 => 
  array (
    'label' => 'Chaîne LSTSAR OPS existante',
    'href' => '/opus-lstsar-manager/chain',
  ),
  1 => 
  array (
    'label' => 'Operations LSTSAR OPS existantes',
    'href' => '/opus-lstsar-manager/operations',
  ),
)), $context);
    }
}