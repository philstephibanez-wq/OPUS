<?php
declare(strict_types=1);

namespace Opus\Manager\Controller;

/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_CORE */
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
            'StepTechnicalArchitecture' => 'Première question obligatoire : Fullstack, Frontend ou Backend.',
            'StepFunctionalSpace' => 'Puis seulement : portail public, frontoffice, backoffice, mixte, espace admin ou espace utilisateur.',
            'StepIdentity' => 'Nom du site, propriétaire, domaine et contexte.',
            'StepTemplate' => 'Choix du modèle OPUS adapté au couple architecture technique + espace fonctionnel.',
            'StepLanguages' => 'Langues officielles UE + ukrainien.',
            'StepApiContract' => 'Contrats API requis si Frontend ou Backend séparé.',
            'StepBackendBinding' => 'Backend associé obligatoire si le choix technique est Frontend.',
            'StepModules' => 'Modules : Ref Book, User Book, ODBC, LSTSAR, auth, logs.',
            'StepSecurity' => 'Users, ACL/RBAC, SSO, politiques d’accès.',
            'StepData' => 'Tables, colonnes, types attendus, contraintes côté backend ou fullstack.',
            'StepOdbc' => 'DSN, drivers et tests ODBC si nécessaire.',
            'StepLstsar' => 'Load / Secure / Transform / Store / Audit si nécessaire.',
            'StepComposerPlan' => 'Plan Composer commun CLI + OPUS Manager avant exécution.',
            'StepComposerInstall' => 'composer validate/install/no-dev/autoload.',
            'StepSmokeTests' => 'Tests post-installation.',
            'StepSummary' => 'Résumé utilisateur, rapport technique, liens Ref Book/User Book.',
        ];

        $html = '<section class="om-card om-primary"><h2>Créer un site avec OPUS</h2><p>Première décision : choisir l’architecture technique du site. Fullstack, Frontend ou Backend. Le wizard déduit ensuite les étapes utiles.</p></section>';
        $html .= '<section class="om-card"><h2>1. Architecture technique</h2><div class="om-steps"><article><strong>Fullstack</strong><p>Application complète adaptée à un portail comme LogAndPlay : contenu, SEO, pages, formulaires contrôlés et séparation interne vues/services/données.</p></article><article><strong>Frontend</strong><p>Couche UI séparée. Backend associé obligatoire, communication via API, ACL/RBAC consommés et SSO/session fédérée.</p></article><article><strong>Backend</strong><p>API, services métier, données, ACL/RBAC, SSO, logs, health/version, ODBC et LSTSAR si nécessaire.</p></article></div></section>';
        $html .= '<section class="om-card"><h2>2. Espace fonctionnel</h2><p>Après le choix technique seulement : portail public, frontoffice, backoffice, mixte, espace admin ou espace utilisateur. Frontend ne signifie pas frontoffice ; backend ne signifie pas backoffice.</p></section>';
        $html .= '<section class="om-steps">';
        foreach ($steps as $step => $description) {
            $html .= '<article><strong>' . $this->h($step) . '</strong><p>' . $this->h($description) . '</p></article>';
        }
        $html .= '</section>';
        $html .= '<section class="om-card"><h2>Règle de réutilisation</h2><p>ODBC Manager, LSTSAR Manager, FSM, ACL/RBAC, SSO et Composer sont des modules orchestrés. Ils ne sont pas recréés dans le wizard.</p></section>';

        return $this->shell($this->title(), $html, $context);
    }
}