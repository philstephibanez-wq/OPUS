<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class ComposerManagerController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/composer';
    }

    public function title(): string
    {
        return 'Composer';
    }

    public function group(): string
    {
        return 'Installation';
    }

    public function isExpert(): bool
    {
        return true;
    }

    public function render(array $context = []): string
    {
        return $this->shell($this->title(), $this->moduleCard('Composer validate/install/no-dev/autoload et packages.', array (
)), $context);
    }
}