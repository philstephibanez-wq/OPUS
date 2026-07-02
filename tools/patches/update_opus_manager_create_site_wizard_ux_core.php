<?php
declare(strict_types=1);

/**
 * OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE
 *
 * Verrouille la démarche utilisateur :
 * - l'entrée principale n'est pas FSM/ACL/ODBC/LSTSAR
 * - l'entrée principale est "Créer un site avec OPUS"
 * - les modules techniques restent disponibles, mais derrière un parcours guidé
 */

$root = getcwd();
$workspace = 'H:\\MAESTRO_WORKSPACE';

if (!is_file($root . DIRECTORY_SEPARATOR . 'composer.json')) {
    fwrite(STDERR, 'OPUS_MANAGER_CREATE_SITE_WIZARD_NOT_IN_OPUS_ROOT' . PHP_EOL);
    exit(1);
}

$dirs = [
    $root . DIRECTORY_SEPARATOR . 'DOC',
    $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'patches',
    $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'smokes',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, 'OPUS_MANAGER_CREATE_SITE_WIZARD_DIR_CREATE_FAILED: ' . $dir . PHP_EOL);
        exit(1);
    }
}

function opus_wizard_write(string $file, string $source): void
{
    if (file_put_contents($file, $source) === false) {
        fwrite(STDERR, 'OPUS_MANAGER_CREATE_SITE_WIZARD_WRITE_FAILED: ' . $file . PHP_EOL);
        exit(1);
    }
}

function opus_wizard_read(string $file): string
{
    if (!is_file($file)) {
        return '';
    }

    $source = file_get_contents($file);
    return is_string($source) ? $source : '';
}

$doc = <<<'MD'
# OPUS Manager — UX Create Site Wizard

Contrat : `OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE`

## Décision produit

OPUS Manager est le backoffice central OPUS, mais son entrée utilisateur principale doit être :

```text
Créer un site avec OPUS
```

L'utilisateur ne doit pas commencer par FSM, ACL, SSO, ODBC, LSTSAR, Composer ou les détails internes.

Ces modules restent indispensables, mais ils doivent être intégrés dans un parcours guidé, lisible et progressif.

## Règle UX

```text
Architecture interne : OPUS Manager modulaire avec un controller par fonctionnalité.
Expérience utilisateur : assistant clair "Créer un site avec OPUS".
```

## Parcours utilisateur principal

```text
OPUS Manager
→ Créer un site
→ Choisir le type de site
→ Choisir le modèle / template
→ Choisir les langues
→ Choisir les modules
→ Configurer identité / sécurité
→ Configurer données / ODBC / LSTSAR si nécessaire
→ Prévisualiser
→ Générer
→ Installer via Composer
→ Lancer les tests
→ Ouvrir le site
→ Lire le User Book
```

## Niveaux de navigation

### Niveau utilisateur

- Créer un site
- Gérer mes sites
- Installer / Mettre à jour
- Documentation
- Diagnostics simples

### Niveau admin / expert

- Users / Identity
- ACL / RBAC
- SSO
- Sessions / Auth audit
- FSM
- CL
- Models
- Database
- ODBC
- LSTSAR
- Composer / Packages
- Logs
- Diagnostics avancés

## CreateSiteController

`CreateSiteController` est le point d'entrée prioritaire pour la création d'un site OPUS.

Responsabilité :

- piloter le parcours de création
- présenter les choix de façon claire
- déléguer aux modules OPUS existants
- ne pas dupliquer la logique métier
- produire un résultat installable via Composer
- produire un résumé utilisateur
- produire une trace technique exploitable par le Ref Book

## Étapes du wizard

```text
CreateSiteController
├─ StepIdentity
├─ StepSiteType
├─ StepTemplate
├─ StepLanguages
├─ StepModules
├─ StepSecurity
├─ StepData
├─ StepOdbc
├─ StepLstsar
├─ StepComposerInstall
├─ StepSmokeTests
└─ StepSummary
```

## Modules OPUS utilisés par le wizard

Le wizard ne recrée pas les briques. Il orchestre les briques OPUS existantes :

- Users / Identity pour le propriétaire, les comptes et les accès
- ACL / RBAC pour les droits du site
- SSO si le site nécessite une fédération d'identité
- FSM pour les états de génération, validation, installation et publication
- CL / Models pour les structures typées
- Database pour les tables, colonnes, contraintes et types attendus
- ODBC Manager pour les connexions externes
- LSTSAR Manager pour Load / Secure / Transform / Store / Audit
- Composer Manager pour l'installation, la mise à jour et l'autoload
- Ref Book pour la documentation technique générée
- User Book pour la documentation utilisateur générée
- Logs / Diagnostics pour les contrôles post-installation

## Exigences i18n

Le Create Site Wizard doit être disponible dans toutes les langues officielles UE + ukrainien (`uk`).

Les textes utilisateur du wizard ne doivent pas exposer de jargon interne inutile.

## Exigences dev/prod

En dev ou staging contrôlé :

- profiler autorisé
- diagnostics détaillés autorisés
- logs techniques accessibles selon droits

En prod :

- aucun profiler
- aucun debug
- aucune toolbar debug
- aucune activation par `profiler=1`
- aucun détail sensible exposé
- auth centrale obligatoire
- ACL/RBAC obligatoire

## Exigences Composer

La création d'un site OPUS doit aboutir à une installation testable :

- `composer validate --strict`
- `composer install`
- `composer install --no-dev`
- `composer dump-autoload --classmap-authoritative`
- smoke post-install
- aucun chemin local `H:\` ou `D:\` dans les packages installables
- aucun cache/temp/log committé

## Ref Book

Le Ref Book doit documenter :

- le contrat du wizard
- les controllers
- les routes
- les modules OPUS appelés
- les manifests
- les packages Composer utilisés
- les tests d'installation

## User Book

Le User Book doit expliquer :

- comment créer un site
- quels choix faire
- comment choisir les langues
- comment activer ou non ODBC / LSTSAR
- comment lancer l'installation
- comment vérifier que le site fonctionne
- comment corriger une erreur utilisateur simple
- quand contacter un administrateur

## Règle finale

OPUS Manager n'est pas une collection de pages techniques.

OPUS Manager est un backoffice clair, précis, ergonomique et intuitif.

Le wizard "Créer un site" est l'entrée principale pour un utilisateur qui veut produire un site avec OPUS.
MD;

$docFile = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX.md';
opus_wizard_write($docFile, $doc . PHP_EOL);

$scopeFile = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'P7_OPS_FINAL_CLOSURE_SCOPE.md';
$scope = opus_wizard_read($scopeFile);
if ($scope === '') {
    $scope = '# OPUS P7 — portée de clôture finale' . PHP_EOL;
}

if (!str_contains($scope, 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE')) {
    $scope .= PHP_EOL;
    $scope .= '## OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE' . PHP_EOL . PHP_EOL;
    $scope .= '- OPUS Manager reste modulaire, mais l’entrée utilisateur principale est `Créer un site avec OPUS`.' . PHP_EOL;
    $scope .= '- Les modules FSM, ACL/RBAC, SSO, ODBC, LSTSAR et Composer sont orchestrés derrière un Create Site Wizard.' . PHP_EOL;
    $scope .= '- Le user ne doit pas commencer par les composants internes.' . PHP_EOL;
    $scope .= '- `CreateSiteController` pilote le parcours et délègue aux briques OPUS existantes.' . PHP_EOL;
    $scope .= '- Le wizard doit produire site installable Composer, Ref Book, User Book, smokes et diagnostic.' . PHP_EOL;
}
opus_wizard_write($scopeFile, $scope);

$controllerFile = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_CONTROLLER_ARCHITECTURE.md';
$controllerDoc = opus_wizard_read($controllerFile);
if ($controllerDoc !== '') {
    if (!str_contains($controllerDoc, 'Règle canonique : un controller par fonctionnalité/page.')) {
        $controllerDoc = str_replace(
            "## Règle\n\n",
            "## Règle\n\nRègle canonique : un controller par fonctionnalité/page.\n\n",
            $controllerDoc
        );
    }

    if (!str_contains($controllerDoc, 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE')) {
        $controllerDoc .= PHP_EOL;
        $controllerDoc .= '## OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE' . PHP_EOL . PHP_EOL;
        $controllerDoc .= '- L’architecture reste `un controller par fonctionnalité/page`.' . PHP_EOL;
        $controllerDoc .= '- L’expérience utilisateur principale commence par `Créer un site avec OPUS`.' . PHP_EOL;
        $controllerDoc .= '- `CreateSiteController` devient l’entrée prioritaire du parcours utilisateur.' . PHP_EOL;
        $controllerDoc .= '- Les controllers FSM, ACL/RBAC, SSO, ODBC, LSTSAR et Composer restent des modules internes ou experts.' . PHP_EOL;
    }

    opus_wizard_write($controllerFile, $controllerDoc);
}

$reuseFile = $root . DIRECTORY_SEPARATOR . 'DOC' . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_REUSE_FOUNDATION.md';
$reuseDoc = opus_wizard_read($reuseFile);
if ($reuseDoc !== '') {
    if (!str_contains($reuseDoc, 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE')) {
        $reuseDoc .= PHP_EOL;
        $reuseDoc .= '## OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE' . PHP_EOL . PHP_EOL;
        $reuseDoc .= '- La réutilisation des briques OPUS doit être orientée utilisateur par le Create Site Wizard.' . PHP_EOL;
        $reuseDoc .= '- ODBC Manager et LSTSAR Manager sont réutilisés comme modules du wizard, pas recréés.' . PHP_EOL;
    }

    opus_wizard_write($reuseFile, $reuseDoc);
}

if (is_dir($workspace)) {
    $workspaceProjectDir = $workspace . DIRECTORY_SEPARATOR . 'CONTEXT' . DIRECTORY_SEPARATOR . 'PROJECTS' . DIRECTORY_SEPARATOR . 'OPUS';
    $workspaceHandoffDir = $workspace . DIRECTORY_SEPARATOR . 'CONTEXT' . DIRECTORY_SEPARATOR . 'HANDOFFS';

    foreach ([$workspaceProjectDir, $workspaceHandoffDir] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    opus_wizard_write($workspaceProjectDir . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX.md', $doc . PHP_EOL);
    opus_wizard_write($workspaceHandoffDir . DIRECTORY_SEPARATOR . 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX.md', $doc . PHP_EOL);
}

echo 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE' . PHP_EOL;
echo 'DOC=DOC/OPUS_MANAGER_CREATE_SITE_WIZARD_UX.md' . PHP_EOL;
echo 'SCOPE=DOC/P7_OPS_FINAL_CLOSURE_SCOPE.md' . PHP_EOL;
echo 'OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_OK' . PHP_EOL;
