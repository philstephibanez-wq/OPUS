<?php
declare(strict_types=1);

/**
 * OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_CORE
 *
 * Verrouille la première question du wizard Créer un site :
 * Fullstack / Frontend / Backend.
 */

$root = getcwd();

if (!is_file($root . DIRECTORY_SEPARATOR . 'composer.json')) {
    fwrite(STDERR, 'OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_NOT_IN_OPUS_ROOT' . PHP_EOL);
    exit(1);
}

$controllerFile = $root . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . 'opus-manager' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Controller' . DIRECTORY_SEPARATOR . 'CreateSiteController.php';
$wizardDocFile = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX.md';
$scopeFile = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'P7_OPS_FINAL_CLOSURE_SCOPE.md';
$newDocFile = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST.md';

foreach ([$controllerFile, $wizardDocFile, $scopeFile] as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, 'OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_FILE_MISSING: ' . $file . PHP_EOL);
        exit(1);
    }
}

function opus_read(string $file): string
{
    $source = file_get_contents($file);
    if (!is_string($source)) {
        fwrite(STDERR, 'OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_READ_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }

    return $source;
}

function opus_write(string $file, string $source): void
{
    if (file_put_contents($file, $source) === false) {
        fwrite(STDERR, 'OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_WRITE_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }
}

$controller = opus_read($controllerFile);

if (!str_contains($controller, 'OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_CORE')) {
    $controller = str_replace(
        '/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE */',
        '/** OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_CORE */',
        $controller
    );
}

$oldSteps = <<<'PHP'
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
PHP;

$newSteps = <<<'PHP'
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
PHP;

if (str_contains($controller, $oldSteps)) {
    $controller = str_replace($oldSteps, $newSteps, $controller);
} elseif (!str_contains($controller, 'StepTechnicalArchitecture')) {
    $controller = preg_replace(
        "/        \\$steps = \\[.*?        \\];/s",
        $newSteps,
        $controller,
        1,
        $count
    );
    if ($count !== 1) {
        fwrite(STDERR, 'OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_STEPS_REPLACE_FAILED' . PHP_EOL);
        exit(1);
    }
}

$oldHero = <<<'PHP'
        $html = '<section class="om-card om-primary"><h2>Créer un site avec OPUS</h2><p>Le wizard est l’entrée principale. Il masque la complexité technique et orchestre les briques OPUS existantes.</p></section>';
PHP;

$newHero = <<<'PHP'
        $html = '<section class="om-card om-primary"><h2>Créer un site avec OPUS</h2><p>Première décision : choisir l’architecture technique du site. Fullstack, Frontend ou Backend. Le wizard déduit ensuite les étapes utiles.</p></section>';
        $html .= '<section class="om-card"><h2>1. Architecture technique</h2><div class="om-steps"><article><strong>Fullstack</strong><p>Application complète adaptée à un portail comme LogAndPlay : contenu, SEO, pages, formulaires contrôlés et séparation interne vues/services/données.</p></article><article><strong>Frontend</strong><p>Couche UI séparée. Backend associé obligatoire, communication via API, ACL/RBAC consommés et SSO/session fédérée.</p></article><article><strong>Backend</strong><p>API, services métier, données, ACL/RBAC, SSO, logs, health/version, ODBC et LSTSAR si nécessaire.</p></article></div></section>';
        $html .= '<section class="om-card"><h2>2. Espace fonctionnel</h2><p>Après le choix technique seulement : portail public, frontoffice, backoffice, mixte, espace admin ou espace utilisateur. Frontend ne signifie pas frontoffice ; backend ne signifie pas backoffice.</p></section>';
PHP;

if (str_contains($controller, $oldHero)) {
    $controller = str_replace($oldHero, $newHero, $controller);
} elseif (!str_contains($controller, '<h2>1. Architecture technique</h2>')) {
    fwrite(STDERR, 'OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_HERO_REPLACE_FAILED' . PHP_EOL);
    exit(1);
}

opus_write($controllerFile, $controller);

$doc = <<<'MD'
# OPUS Manager — Create Site Technical Type First

Contrat : `OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_CORE`

## Décision

Dans `Créer un site`, la première question doit être l'architecture technique :

```text
Fullstack
Frontend
Backend
```

L'espace fonctionnel vient ensuite seulement :

```text
portail public
frontoffice
backoffice
mixte
espace admin
espace utilisateur
```

## Règle

```text
Fullstack / Frontend / Backend = architecture technique
Frontoffice / Backoffice / Portail = espace fonctionnel
```

Ne jamais confondre les deux axes.

## Ordre du wizard

```text
CreateSiteController
├─ StepTechnicalArchitecture
│  ├─ Fullstack
│  ├─ Frontend
│  └─ Backend
├─ StepFunctionalSpace
├─ StepIdentity
├─ StepTemplate
├─ StepLanguages
├─ StepApiContract
├─ StepBackendBinding
├─ StepModules
├─ StepSecurity
├─ StepData
├─ StepOdbc
├─ StepLstsar
├─ StepComposerPlan
├─ StepComposerInstall
├─ StepSmokeTests
└─ StepSummary
```

## Branches

### Fullstack

Cas de référence : LogAndPlay.

- portail de contenu
- SEO
- pages publiques
- formulaires contrôlés
- séparation interne vues / services / données
- application complète générable par Composer

### Frontend

- backend associé obligatoire
- API obligatoire
- ACL/RBAC consommés
- SSO ou session fédérée
- aucun accès direct aux données métier

### Backend

- API
- services métier
- données
- ACL/RBAC portés ici
- SSO porté ici
- ODBC/LSTSAR possibles
- health/version/logs obligatoires

## CLI et OPUS Manager

La décision technique doit être utilisable :

```text
via CLI
via OPUS Manager
```

Les deux entrées doivent produire le même plan de création.
MD;

opus_write($newDocFile, $doc . PHP_EOL);

$wizardDoc = opus_read($wizardDocFile);
if (!str_contains($wizardDoc, 'OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_CORE')) {
    $wizardDoc .= PHP_EOL;
    $wizardDoc .= '## OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_CORE' . PHP_EOL . PHP_EOL;
    $wizardDoc .= '- La première question de `Créer un site` est l’architecture technique : Fullstack, Frontend ou Backend.' . PHP_EOL;
    $wizardDoc .= '- L’espace fonctionnel vient ensuite : portail public, frontoffice, backoffice, mixte, admin ou utilisateur.' . PHP_EOL;
    $wizardDoc .= '- `Frontend` ne signifie pas `frontoffice` ; `backend` ne signifie pas `backoffice`.' . PHP_EOL;
    $wizardDoc .= '- Si le choix est `Frontend`, un backend associé et un contrat API sont obligatoires.' . PHP_EOL;
    $wizardDoc .= '- Le plan Composer doit être commun à la CLI et à OPUS Manager.' . PHP_EOL;
}
opus_write($wizardDocFile, $wizardDoc);

$scope = opus_read($scopeFile);
if (!str_contains($scope, 'OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_CORE')) {
    $scope .= PHP_EOL;
    $scope .= '## OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_CORE' . PHP_EOL . PHP_EOL;
    $scope .= '- Dans `Créer un site`, la première question est l’architecture technique : Fullstack, Frontend ou Backend.' . PHP_EOL;
    $scope .= '- L’espace fonctionnel vient ensuite seulement.' . PHP_EOL;
    $scope .= '- Le wizard doit conserver deux axes indépendants : architecture technique et espace fonctionnel.' . PHP_EOL;
    $scope .= '- Frontend ne signifie pas frontoffice ; backend ne signifie pas backoffice.' . PHP_EOL;
    $scope .= '- Frontend impose backend associé + API + ACL/RBAC consommés + SSO/session fédérée.' . PHP_EOL;
    $scope .= '- Backend porte API, métier, données, ACL/RBAC, SSO, health/version/logs.' . PHP_EOL;
    $scope .= '- Fullstack reste adapté aux portails de contenu comme LogAndPlay.' . PHP_EOL;
}
opus_write($scopeFile, $scope);

echo 'OPUS_MANAGER_CREATE_SITE_TECH_TYPE_FIRST_CORE_OK' . PHP_EOL;
