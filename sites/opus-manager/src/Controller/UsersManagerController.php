<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class UsersManagerController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/users';
    }

    public function title(): string
    {
        return 'Users / Identity';
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
        return $this->shell($this->title(), $this->moduleCard('Utilisateurs, comptes, identité et état des accès.', array (
)), $context);
    }
}