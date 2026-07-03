<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class LogsController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/logs';
    }

    public function title(): string
    {
        return 'Logs';
    }

    public function group(): string
    {
        return 'Exploitation';
    }

    public function isExpert(): bool
    {
        return true;
    }

    public function render(array $context = []): string
    {
        return $this->shell($this->title(), $this->moduleCard('Accès contrôlé aux journaux autorisés.', array (
)), $context);
    }
}