<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class OpusManagerDashboardController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager';
    }

    public function title(): string
    {
        return 'Dashboard';
    }

    public function group(): string
    {
        return 'Accueil';
    }

    public function isExpert(): bool
    {
        return false;
    }

    public function render(array $context = []): string
    {
        $html = '<section class="om-card om-primary"><h2>Créer un site</h2><p>Pour un utilisateur, le premier parcours est le wizard de création de site.</p><div class="om-actions"><a href="/opus-manager/create-site">Démarrer le wizard</a></div></section>';
        $html .= '<section class="om-card"><h2>Administration</h2><p>Les modules techniques restent disponibles pour les administrateurs et experts, avec un controller dédié par fonctionnalité.</p></section>';
        return $this->shell($this->title(), $html, $context);
    }
}