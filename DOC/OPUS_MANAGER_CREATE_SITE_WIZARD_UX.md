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
