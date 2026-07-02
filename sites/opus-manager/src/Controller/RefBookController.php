<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class RefBookController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/ref-book';
    }

    public function title(): string
    {
        return 'Ref Book';
    }

    public function group(): string
    {
        return 'Documentation';
    }

    public function isExpert(): bool
    {
        return false;
    }

    public function render(array $context = []): string
    {
        return $this->shell($this->title(), $this->moduleCard('Documentation technique, API, classes, routes et manifests.', array (
)), $context);
    }
}