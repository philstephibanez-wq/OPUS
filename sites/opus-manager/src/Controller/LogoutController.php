<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

use Opus\Manager\Service\OpusManagerAuth;
use Opus\Manager\Service\OpusManagerI18n;

/** OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE OPUS_MANAGER_AUTH_I18N_FINALIZE_CORE */
final class LogoutController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/logout';
    }

    public function title(): string
    {
        return 'Logout';
    }

    public function group(): string
    {
        return 'Identity';
    }

    public function isExpert(): bool
    {
        return false;
    }

    public function render(array $context = []): string
    {
        $lang = OpusManagerI18n::resolveLang((string) ($context['lang'] ?? ($_GET['lang'] ?? 'fr')));
        OpusManagerAuth::signOut();
        header('Location: /opus-manager/sign-in?lang=' . rawurlencode($lang), true, 302);

        return '';
    }
}