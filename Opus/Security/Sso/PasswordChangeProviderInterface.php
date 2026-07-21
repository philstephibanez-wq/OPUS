<?php
declare(strict_types=1);

namespace Opus\Security\Sso;

interface PasswordChangeProviderInterface
{
    public function changePassword(
        string $subject,
        string $currentPassword,
        string $newPassword
    ): SsoIdentity;
}
