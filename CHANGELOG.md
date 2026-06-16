# CHANGELOG Ã¢â‚¬â€ P112C5

## Ajouts:
- Squelette HTML navigable du Reference Book Opus.
- Pages HTML : accueil, architecture, FSM, ACL.
- CSS/JS dédiés ÃƒÂ  la documentation générée.
- Navigation JSON séparée.

## P112Q2I0_OPUS_GITHUB_BOOTSTRAP
- Prepared Opus for private GitHub publication.
- Added bootstrap documentation and automation wrappers.
- Added marked `.gitignore` block for secrets, runtime data and future Lstsa run outputs.

## P112Q2I1_OPUS_SITE_MULTI_DB_AND_Lstsa_CONTRACT
- Added site multi-database configuration collection and loader.
- Added first public Lstsa namespace and XML contract loader.
- Added field constraints with type, length and byte validation.
- Added append-only JSON/Markdown report archive writer.
- Added automation smoke recipe.

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I2_OPUS_Lstsa_RUNNER_SCHEDULER_BASELINE -->
## P112Q2I2_OPUS_Lstsa_RUNNER_SCHEDULER_BASELINE

- Ajout du runner CLI Lstsa baseline hors requÃƒÂªte HTTP.
- Ajout du scheduler baseline pour créer une demande de run Lstsa.
- Ajout queue/locks/heartbeats fichier sous `var/lstsa/` hors Git.
- Ajout recette smoke test avec rapport JSON/MD append-only.
<!-- END MAESTRO_WORKSPACE P112Q2I2_OPUS_Lstsa_RUNNER_SCHEDULER_BASELINE -->

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I3_OPUS_Lstsa_BATCH_CHECKPOINT_EXECUTOR -->
## P112Q2I3_OPUS_Lstsa_BATCH_CHECKPOINT_EXECUTOR

- Ajout du premier exécuteur Lstsa batch/checkpoint.
- Validation stricte input puis output après transformation.
- Quarantine runtime pour lignes rejetées.
- Archives runtime append-only pour lignes stockées.
- Rapports JSON/MD enrichis avec artifacts.
<!-- END MAESTRO_WORKSPACE P112Q2I3_OPUS_Lstsa_BATCH_CHECKPOINT_EXECUTOR -->

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I4_OPUS_Lstsa_REPORTS_ARCHIVES_CATALOG -->
## P112Q2I4_OPUS_Lstsa_REPORTS_ARCHIVES_CATALOG

- Ajout du catalogue Lstsa des rapports, archives, quarantaines et checkpoints.
- Ajout de snapshots JSON/Markdown append-only sous `var/lstsa/reports/_index`.
- Ajout du CLI `bin/opus-lstsa-reports.cmd`.
- Maintien volontaire de la convention PHP `Lstsa*`.
<!-- END MAESTRO_WORKSPACE P112Q2I4_OPUS_Lstsa_REPORTS_ARCHIVES_CATALOG -->

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I5_OPUS_Lstsa_FSM_BACKGROUND_STAGING -->
## P112Q2I5_OPUS_Lstsa_FSM_BACKGROUND_STAGING

- Ajout du contrÃ´le FSM explicite du runner Lstsa background.
- Ajout des objets de phase Load / Secure input / Transform / Secure output / Store / Archive / Report.
- Ajout dâ€™un flux SQLite source -> staging cible -> commit final cible, avec suppression du staging en succÃ¨s ou èchec.
- Ajout des èvènements append-only OK / FAIL sous `var/lstsa/events/`.
- Ajout dâ€™une recette de validation hors HTTP avec BDD source/cible SQLite temporaires.
<!-- END MAESTRO_WORKSPACE P112Q2I5_OPUS_Lstsa_FSM_BACKGROUND_STAGING -->

<!-- BEGIN MAESTRO_WORKSPACE P112Q2J_OPUS_GLOBAL_RECIPE_SUITE -->
## P112Q2J_OPUS_GLOBAL_RECIPE_SUITE

- Added a manifest-driven global Opus recipe suite.
- Added reusable recipe runner, context, result and report objects.
- Added life robot scenario framework for simulated user/system flows.
- Added technical coverage for core, database, FSM, ACL, I18N, routing, templates and Lstsa/LSTSAR.
- Added ignored runtime report storage under `var/recipes/`.
<!-- END MAESTRO_WORKSPACE P112Q2J_OPUS_GLOBAL_RECIPE_SUITE -->


<!-- BEGIN MAESTRO_WORKSPACE P112Q2J2_OPUS_GLOBAL_EVOLUTIVE_HTTP_MAIL_LIFE_RECIPE -->
## P112Q2J2_OPUS_GLOBAL_EVOLUTIVE_HTTP_MAIL_LIFE_RECIPE

- Replaces the rejected minimal HTTP witness with a real evolutive HTTP/Mail life recipe.
- Adds an anti-regression feature manifest under `tools/recipes/manifest/`.
- Adds `MailRecipe` and `FeatureManifestRecipe` to the global suite.
- Adds a visible rich recipe dashboard driven by real local HTTP GET/POST requests.
- Adds MailRobot sandbox send/receive validation and LSTSAR HTTP scheduling/background execution validation.
<!-- END MAESTRO_WORKSPACE P112Q2J2_OPUS_GLOBAL_EVOLUTIVE_HTTP_MAIL_LIFE_RECIPE -->


## P112Q2J3_OPUS_RECIPE_LIVE_MOVIE_DASHBOARD

- Added live movie dashboard behaviour to the Opus global HTTP/mail life recipe.
- Added polling timeline, current actor/action, progress bar, HTTP transcript, MailRobot inbox and LSTSAR event stream.
- Added `OPUS_LIVE_MOVIE_DASHBOARD_OK` as a visible dashboard contract marker.
## P112Q2J4_OPUS_REAL_MAILPIT_LIVE_RECIPE

- Replaced local JSON-only mail robot proof with real Mailpit SMTP/API delivery checks.
- Added Mailpit availability, send, receive and content assertions to the global Opus recipe.
- Added real Mailpit markers to the visible live recipe dashboard.


## P112Q2K_OPUS_REAL_FEATURES_RECIPE_BINDING

- Added a mandatory real feature binding recipe for OPUS_REF_BOOK.
- Added HTTP checks for historical Opus pages: `/`, `/auto-recipe`, `/panther-browser-testing`, `/total-apache-recipe`, `/opus-ui-functional-target.html`.
- Added historical Mailpit recipe checks through `opus-mail-recipe.php?scenario=one|two|three&transport=mailpit_smtp`.
- Registered real feature binding in the global feature manifest and recipe manifest.

## P112Q2K1_OPUS_AUTOLOADER_CACHE_CONTRACT

- Ajout du contrat officiel `Opus\Autoload`.
- Gènèration du cache `var/cache/opus/autoload/opus_classmap.php`.
- Recette `OPUS_AUTOLOADER_CACHE_OK`.

## P112Q2L_OPUS_REAL_REFBOOK_HTTP_DIAGNOSTICS

- Ajout d'artefacts diagnostic HTTP pour la recette rèelle `OPUS_REF_BOOK`.
- Chaque page testèe ècrit un JSON et un corps brut dans `var/recipes/.../real_feature_binding/diagnostics`.
- La recette globale expose `OPUS_REAL_FEATURE_BINDING_DIAGNOSTICS_OK`.

