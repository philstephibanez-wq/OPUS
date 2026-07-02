# OPUS P7 — portée de clôture finale

Date de mise à jour : 2026-07-02

## État connu

- Repo principal : `H:\OPUS`
- Dernier état constaté : `master...origin/master` propre
- Dernier commit constaté : `d86c8d2 Fix P7 OPS profiler open context`
- Ref Book trouvé dans OPUS : `H:\OPUS\packages\opus-ref-book`
- Repo séparé `H:\OPUS_REF_BOOK` : absent lors de l'audit
- User Book : non trouvé lors de l'audit, doit être créé ou complété
- Logs runtime OPS :
  - `H:\OPUS\var\logs\opus_lstsar-manager\access.log`
  - `H:\OPUS\var\logs\opus_lstsar-manager\auth.log`
  - `H:\OPUS\var\logs\opus_lstsar-manager\php-server.log`
  - `H:\OPUS\var\logs\opus_lstsar-manager\profiler.log`

## Chaîne fonctionnelle OPUS / OPS / LSTSAR à documenter et verrouiller

```text
Auth / SSO éventuel
→ RBAC / policies
→ FSM
→ CL
→ Models
→ Database / Tables / Colonnes / Contraintes / Types attendus
→ ODBC Manager / DSN / Drivers / Tests de connexion
→ LSTSAR : Load / Secure / Transform / Store / Audit
→ Actions : preview / dry-run / exécution contrôlée
→ Logs / Profiler / Diagnostics
```

## Clôture OPS app

À vérifier avant clôture :

- une seule navigation professionnelle visible sur toutes les pages OPS
- pas de double menu
- pas de double toolbar profiler
- pas d'ancien panneau `ops-profiler-panel` visible
- `profiler=1` active un mode visiblement différent
- mode profiler persistant en session
- sortie profiler OK
- bouton `Open profiler` utile depuis une page app
- bouton `Back to app` utile depuis `/profiler`
- `site` et `lang` conservés dans la navigation
- login/logout/sign-in contrôlé
- environnement dev/prod documenté
- aucun fallback silencieux
- logs applicatifs et profiler documentés
- aucun fichier `.log` committé

## Ref Book

À produire ou mettre à jour :

- `packages/opus-ref-book/DOC/P7_OPS_REFBOOK_ALIGNMENT.md`

À vérifier :

- routes OPUS documentées
- classes réelles uniquement
- aucun symbole mort
- lien vers FSM / CL / Models / Database / ODBC Manager / LSTSAR
- profiler documenté
- auth/login/logout documentés
- logs documentés
- génération HTML OK si génération RefBook disponible
- manifest source à jour si manifest disponible

## User Book

À produire ou mettre à jour :

- `sites/opus-p7-ops/USER_BOOK.md`

Contenu minimum :

- se connecter / se déconnecter
- différence dev/prod
- activer le profiler
- désactiver le profiler
- lire la toolbar profiler
- ouvrir la page profiler
- comprendre la chaîne LSTSAR complète
- utiliser Operations
- utiliser Command Center
- accéder à Models
- accéder à ODBC Manager
- lire les logs
- signaler un problème
- éviter le jargon interne inutile

## Installation serveur OPUS

Scénario A : installation depuis le repo complet

```text
git clone OPUS
composer validate --strict
composer install
composer dump-autoload
serveur web pointant vers sites/opus-p7-ops/public
smoke après installation
```

Scénario B : installation production / serveur

```text
composer validate --strict
composer install --no-dev
composer dump-autoload --classmap-authoritative
smoke après installation no-dev
```

Contraintes :

- test dans un répertoire temporaire isolé
- suppression automatique du répertoire temporaire après succès
- aucun cache/temp/vendor temporaire conservé dans le repo
- aucun chemin local `H:\` ou `D:\` dans les packages installables
- aucune dépendance implicite non déclarée
- aucun fallback silencieux

## Installation packages serveur via Composer

À vérifier :

- `composer validate --strict`
- `composer show -p`
- `composer install`
- `composer install --no-dev`
- `composer dump-autoload --classmap-authoritative`
- packages OPUS déclarés
- autoload PSR-4 correct
- `packages/opus-ref-book` installable ou documenté
- absence de chemins locaux Windows dans les packages
- absence de logs/caches temporaires dans Git
- smoke OPUS après installation propre

## Artifacts finaux attendus

- `DOC/P7_OPS_FINAL_CLOSURE_AUDIT.md`
- `DOC/P7_OPS_SERVER_INSTALL_TESTS.md`
- `DOC/P7_OPS_COMPOSER_PACKAGES_INSTALL_TESTS.md`
- `sites/opus-p7-ops/USER_BOOK.md`
- `packages/opus-ref-book/DOC/P7_OPS_REFBOOK_ALIGNMENT.md`
- `tools/smokes/smoke_p7_ops_final_books_install_audit_core.php`
- `tools/smokes/smoke_p7_ops_server_composer_install_core.php`

## Smoke final attendu

- routes principales HTTP 200
- login/logout OK
- profiler ON/OFF/session OK
- `Open profiler` / `Back to app` OK
- navigation unique visible
- aucune ancienne toolbar visible
- aucune sortie HTML parasite pendant les smokes
- chain/models/odbc/fsm/cl/profiler accessibles
- Ref Book aligné
- User Book présent
- Composer validate/install/no-dev OK
- pas de `.log` ajouté au commit
- git clean après push

## Prochaine brique recommandée

```text
P7_OPS_FINAL_BOOKS_INSTALL_AUDIT_CORE
```

Objectif : audit final, documentation RefBook/UserBook, tests installation serveur + packages Composer, smokes, rapport de clôture, commit/push, puis tag éventuel.

## OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE

- OPUS Manager impose un controller par fonctionnalité/page.
- Le shell orchestre ; chaque controller porte une responsabilité métier.
- Les controllers cibles couvrent Users/Identity, ACL/RBAC, SSO, FSM, CL, Models, Database, ODBC, LSTSAR, Composer, Create Site, Create Package, Ref Book, User Book, Logs et Diagnostics.
- Les briques existantes doivent être récupérées avant toute création.

## OPUS_MANAGER_CONTROLLER_ARCHITECTURE_CORE_FIX

- La règle canonique OPUS Manager est : `un controller par fonctionnalité/page`.

## OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE

- OPUS Manager reste modulaire, mais l’entrée utilisateur principale est `Créer un site avec OPUS`.
- Les modules FSM, ACL/RBAC, SSO, ODBC, LSTSAR et Composer sont orchestrés derrière un Create Site Wizard.
- Le user ne doit pas commencer par les composants internes.
- `CreateSiteController` pilote le parcours et délègue aux briques OPUS existantes.
- Le wizard doit produire site installable Composer, Ref Book, User Book, smokes et diagnostic.

## OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_FIX

- Verrouille la formulation canonique : `un controller par fonctionnalité/page`.
- Le wizard reste l’entrée utilisateur principale, les controllers restent séparés par fonctionnalité.

## OPUS_MANAGER_CREATE_SITE_WIZARD_UX_CORE_FIX2

- Correction du smoke Create Site Wizard : phrase exacte controller ajoutée.

## OPUS_MANAGER_CONTROLLER_SHELL_REUSE_CORE

- Crée le shell OPUS Manager.
- L’entrée principale utilisateur est `Créer un site avec OPUS`.
- Un controller par fonctionnalité/page.
- ODBC Manager et LSTSAR Manager sont réutilisés via les routes OPUS existantes.
- Le shell n’importe aucune pile externe.
- En prod : aucun profiler/debug.

## OPUS_MANAGER_SHELL_AUTH_PROD_I18N_CORE

- OPUS Manager fait partie de la livraison dev OPUS.
- Auth centrale minimale ajoutée au shell.
- `SignInController` et `LogoutController` sont des controllers dédiés.
- En production : aucun profiler/debug, même avec `profiler=1`.
- I18N prête pour toutes les langues officielles UE + ukrainien (`uk`).

## OPUS_MANAGER_LANGUAGE_SELECTOR_DEDUP_CORE

- Le sélecteur de langue suffit.
- Le shell OPUS Manager ne doit pas répéter `Langue : ...` quand le selecteur est visible.
- Sign in conserve le selecteur, sans badge langue redondant.

## OPUS_MANAGER_ARCHITECTURE_AXES_CORE

- OPUS Manager doit traiter deux axes indépendants : axe technique et axe fonctionnel.
- Axe technique : frontend, backend, API, services, données.
- Axe fonctionnel : frontoffice, backoffice, portail, espace admin, espace utilisateur.
- Frontend ne signifie pas frontoffice ; backend ne signifie pas backoffice.
- Un backoffice peut être client/server ; un frontoffice peut aussi être client/server.
- LogAndPlay reste le cas de référence fullstack / portail de contenu.
- Le futur KB reste le cas de référence client/server frontend + backend.
- Le Create Site Wizard doit demander séparément espace fonctionnel et architecture technique.
