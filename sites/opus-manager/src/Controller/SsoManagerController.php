<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class SsoManagerController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/sso';
    }

    public function title(): string
    {
        return 'SSO';
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
        return $this->shell($this->title(), $this->moduleCard('Providers SSO et configuration de fédération d’identité.', array (
)), $context);
    }
}