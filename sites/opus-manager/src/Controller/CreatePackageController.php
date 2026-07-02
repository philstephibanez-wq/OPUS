<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class CreatePackageController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/create-package';
    }

    public function title(): string
    {
        return 'Créer un package';
    }

    public function group(): string
    {
        return 'Créer';
    }

    public function isExpert(): bool
    {
        return false;
    }

    public function render(array $context = []): string
    {
        return $this->shell($this->title(), $this->moduleCard('Préparation contrôlée d’un package OPUS via recettes Composer.', array (
)), $context);
    }
}