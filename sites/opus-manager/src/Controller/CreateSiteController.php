<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */
final class CreateSiteController extends AbstractOpusManagerController
{
    public function route(): string
    {
        return '/opus-manager/create-site';
    }

    public function title(): string
    {
        return 'Créer un site';
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
        $steps = [
            'StepIdentity' => 'Nom du site, propriétaire, domaine et contexte.',
            'StepSiteType' => 'Type de site : portail, backoffice, démo, documentation ou application métier.',
            'StepTemplate' => 'Choix du modèle OPUS.',
            'StepLanguages' => 'Langues officielles UE + ukrainien.',
            'StepModules' => 'Modules : Ref Book, User Book, ODBC, LSTSAR, auth, logs.',
            'StepSecurity' => 'Users, ACL/RBAC, SSO, politiques d’accès.',
            'StepData' => 'Tables, colonnes, types attendus, contraintes.',
            'StepOdbc' => 'DSN, drivers et tests ODBC si nécessaire.',
            'StepLstsar' => 'Load / Secure / Transform / Store / Audit si nécessaire.',
            'StepComposerInstall' => 'composer validate/install/no-dev/autoload.',
            'StepSmokeTests' => 'Tests post-installation.',
            'StepSummary' => 'Résumé utilisateur, rapport technique, liens Ref Book/User Book.',
        ];

        $html = '<section class="om-card om-primary"><h2>Créer un site avec OPUS</h2><p>Le wizard est l’entrée principale. Il masque la complexité technique et orchestre les briques OPUS existantes.</p></section>';
        $html .= '<section class="om-steps">';
        foreach ($steps as $step => $description) {
            $html .= '<article><strong>' . $this->h($step) . '</strong><p>' . $this->h($description) . '</p></article>';
        }
        $html .= '</section>';
        $html .= '<section class="om-card"><h2>Règle de réutilisation</h2><p>ODBC Manager, LSTSAR Manager, FSM, ACL/RBAC, SSO et Composer sont des modules orchestrés. Ils ne sont pas recréés dans le wizard.</p></section>';

        return $this->shell($this->title(), $html, $context);
    }
}