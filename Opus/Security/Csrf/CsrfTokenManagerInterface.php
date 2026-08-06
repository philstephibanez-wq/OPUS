<?php
declare(strict_types=1);

namespace Opus\Security\Csrf;

interface CsrfTokenManagerInterface extends
    \Opus\Framework\OpusFrameworkComponentInterface,
    \Opus\Framework\OpusExceptionAwareInterface,
    \Opus\Framework\OpusProfilerAwareInterface,
    \Opus\Framework\OpusSelfDocumentingInterface
{
    public function issue(string $scope): string;

    public function assertValid(string $scope, string $token): void;
}
