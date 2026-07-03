<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class RbacManagerController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/rbac';
    }

    public function title(): string
    {
        return 'Rôles & habilitations (RBAC)';
    }

    public function group(): string
    {
        return 'Identité';
    }

    public function isExpert(): bool
    {
        return true;
    }

    public function render(array $context = []): string
    {
        return $this->shell($this->title(), $this->moduleCard('Gestion des rôles, héritages de rôles et assignations aux utilisateurs.', array (
)), $context);
    }
}