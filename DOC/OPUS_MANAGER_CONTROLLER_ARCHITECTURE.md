# OPUS Manager — architecture controllers

Contrat : `OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE`

## Règle

OPUS Manager doit avoir autant de controllers dédiés que de pages/fonctionnalités métier.

Aucun gros controller fourre-tout ne doit porter Users, FSM, ACL, SSO, ODBC, LSTSAR et Composer en même temps.

Le shell OPUS Manager orchestre la navigation et le layout. Chaque controller porte une responsabilité claire.

## Contraintes

- Un controller par fonctionnalité/page.
- Réutiliser les briques OPUS existantes.
- Ne pas recréer LSTSAR Manager ou ODBC Manager.
- Ne pas importer de pile externe.
- Auth centrale obligatoire.
- ACL/RBAC centralisé.
- SSO prévu comme module.
- En production : aucun profiler/debug.
- I18N : toutes langues officielles UE + ukrainien (`uk`).
- Ref Book et User Book obligatoires.

## Controllers cibles

| Controller | Module | Route | Responsabilité |
| --- | --- | --- | --- |
| OpusManagerDashboardController | Dashboard | /opus-manager | Vue d’ensemble du backoffice OPUS Manager. |
| UsersManagerController | Users / Identity | /opus-manager/users | Utilisateurs, identité, comptes, état des accès. |
| AclManagerController | ACL / RBAC | /opus-manager/acl | Permissions, rôles, policies et droits par module. |
| RbacManagerController | RBAC | /opus-manager/rbac | Rôles métiers, héritage et assignations. |
| SsoManagerController | SSO | /opus-manager/sso | Providers SSO, configuration et état des connecteurs. |
| SessionsManagerController | Sessions | /opus-manager/sessions | Sessions actives, révocation et contrôle d’accès. |
| AuthAuditController | Auth Audit | /opus-manager/auth-audit | Audit des connexions, déconnexions et décisions d’accès. |
| FsmManagerController | FSM | /opus-manager/fsm | Machines d’état, transitions et diagnostics FSM. |
| ClManagerController | CL | /opus-manager/cl | CL et orchestration des couches OPUS associées. |
| ModelsManagerController | Models | /opus-manager/models | Modèles, schémas, objets typés et diagnostics. |
| DatabaseManagerController | Database | /opus-manager/database | Tables, colonnes, contraintes, types attendus et sources. |
| OdbcManagerController | ODBC Manager | /opus-manager/odbc | DSN, drivers, tests de connexion et contrats ODBC. |
| LstsarManagerController | LSTSAR Manager | /opus-manager/lstsar | Load / Secure / Transform / Store / Audit. |
| ComposerManagerController | Composer | /opus-manager/composer | Composer validate/install/no-dev/autoload et packages. |
| CreateSiteController | Create Site | /opus-manager/create-site | Création contrôlée de sites OPUS via recettes Composer. |
| CreatePackageController | Create Package | /opus-manager/create-package | Création contrôlée de packages OPUS via recettes Composer. |
| RefBookController | Ref Book | /opus-manager/ref-book | Documentation technique, API, classes, routes et manifests. |
| UserBookController | User Book | /opus-manager/user-book | Documentation utilisateur, parcours, écrans et exploitation. |
| LogsController | Logs | /opus-manager/logs | Accès contrôlé aux journaux autorisés. |
| DiagnosticsController | Diagnostics | /opus-manager/diagnostics | Santé système, contrôles et diagnostics non sensibles. |

## Controllers existants détectés

- `packages/opus-lstsar-manager/src/Controller/DashboardController.php`
- `packages/opus-lstsar-manager/src/Controller/DeclarationsController.php`
- `packages/opus-lstsar-manager/src/Controller/DryRunController.php`
- `packages/opus-lstsar-manager/src/Controller/OperationsController.php`
- `packages/opus-odbc-manager/src/Controller/CrudController.php`
- `packages/opus-odbc-manager/src/Controller/DashboardController.php`
- `packages/opus-odbc-manager/src/Controller/DataSourcesController.php`
- `packages/opus-odbc-manager/src/Controller/LstsarDraftController.php`
- `packages/opus-odbc-manager/src/Controller/PreviewController.php`
- `packages/opus-odbc-manager/src/Controller/TableController.php`
- `packages/opus-odbc-manager/src/Controller/TablesController.php`

## Stratégie de migration

1. Auditer les controllers et pages déjà présents.
2. Raccorder les pages existantes dans le shell OPUS Manager.
3. Créer uniquement les controllers manquants.
4. Déléguer la logique aux services/briques OPUS existants.
5. Ajouter les routes OPUS Manager sans casser les routes historiques.
6. Documenter chaque controller dans le Ref Book.
7. Expliquer chaque écran dans le User Book.

## Prochaine brique

`OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE`

Objectif : créer le shell backoffice OPUS Manager et y brancher les controllers dédiés, en récupérant les pages existantes.


## OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE_FIX

- Ajoute la formulation canonique smokeable : `un controller par fonctionnalité/page`.
