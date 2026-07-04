<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class OdbcManagerController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/odbc';
    }

    public function title(): string
    {
        return 'ODBC Manager';
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
        return $this->shell($this->title(), $this->moduleCard('DSN, drivers, tests de connexion et contrats ODBC.', array (
  0 => 
  array (
    'label' => 'ODBC Manager OPS existant',
    'href' => '/opus-lstsar-manager/odbc-manager',
  ),
)), $context);
    }
}