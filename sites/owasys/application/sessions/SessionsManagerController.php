<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class SessionsManagerController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/sessions';
    }

    public function title(): string
    {
        return 'Sessions';
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
        return $this->shell($this->title(), $this->moduleCard('Sessions actives, révocation et contrôle.', array (
)), $context);
    }
}