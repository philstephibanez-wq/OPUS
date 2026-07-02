<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class AuthAuditController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/auth-audit';
    }

    public function title(): string
    {
        return 'Auth Audit';
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
        return $this->shell($this->title(), $this->moduleCard('Audit des connexions, déconnexions et décisions d’accès.', array (
)), $context);
    }
}