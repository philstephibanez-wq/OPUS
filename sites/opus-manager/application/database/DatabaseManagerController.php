<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class DatabaseManagerController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/database';
    }

    public function title(): string
    {
        return 'Database';
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
        return $this->shell($this->title(), $this->moduleCard('Tables, colonnes, contraintes, types attendus et sources.', array (
)), $context);
    }
}