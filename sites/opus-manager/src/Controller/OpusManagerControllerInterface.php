<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
interface OpusManagerControllerInterface
{
    public function route(): string;

    public function title(): string;

    public function group(): string;

    public function isExpert(): bool;

    public function render(array $context = []): string;
}