<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class FsmManagerController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/fsm';
    }

    public function title(): string
    {
        return 'FSM';
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
        return $this->shell($this->title(), $this->moduleCard('Machines d’état, transitions et diagnostics FSM.', array (
  0 => 
  array (
    'label' => 'Route OPS FSM existante',
    'href' => '/opus-lstsar-manager/fsm',
  ),
)), $context);
    }
}