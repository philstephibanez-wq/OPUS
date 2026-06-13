# PATCH ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â P112Q2I1 Opus Site Multi-DB and Lstsa Contract

## Role
Add the first Opus contract layer for site multi-database declarations and Lstsa.

## Added
- `ASAP\Database\DatabaseConnectionsConfig`
- `ASAP\Database\DatabaseMultiConfigLoader`
- `ASAP\Lstsa\LstsaException`
- `ASAP\Lstsa\LstsaFieldConstraint`
- `ASAP\Lstsa\LstsaFieldMapping`
- `ASAP\Lstsa\LstsaDefinition`
- `ASAP\Lstsa\LstsaConfigLoader`
- `ASAP\Lstsa\LstsaReport`
- `ASAP\Lstsa\LstsaArchiveWriter`
- Lstsa smoke recipe and automation check.

## Contract
Lstsa means Load / Secure / Transform / Store / Archive.
Input and output field constraints include type, required, length, byte size, enum, regex and numeric bounds.
Reports are JSON + Markdown and archive writing is append-only.

## Not done here
No long runner is started from Apache. The runner/scheduler must be introduced in the next palier.

## Next
`P112Q2I2_OPUS_Lstsa_RUNNER_SCHEDULER_FOUNDATION`

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I2_OPUS_Lstsa_RUNNER_SCHEDULER_BASELINE -->
## P112Q2I2_OPUS_Lstsa_RUNNER_SCHEDULER_BASELINE

- CrÃƒÂ©e `ASAP\Lstsa\LstsaRunStatus`.
- CrÃƒÂ©e `ASAP\Lstsa\LstsaRunStore`.
- CrÃƒÂ©e `ASAP\Lstsa\LstsaScheduler`.
- CrÃƒÂ©e `ASAP\Lstsa\LstsaRunner`.
- CrÃƒÂ©e `bin/opus-lstsa-runner.cmd` et `bin/opus-lstsa-scheduler.cmd`.
- Ajoute ignores runtime queue/locks/heartbeats.
<!-- END MAESTRO_WORKSPACE P112Q2I2_OPUS_Lstsa_RUNNER_SCHEDULER_BASELINE -->

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I3_OPUS_Lstsa_BATCH_CHECKPOINT_EXECUTOR -->
## P112Q2I3_OPUS_Lstsa_BATCH_CHECKPOINT_EXECUTOR

- Ajoute `Opus\\Lstsa\\LstsaBatchExecutor`.
- Ãƒâ€°tend `Opus\\Lstsa\\LstsaRunStore` avec checkpoints/archives/quarantine.
- Ãƒâ€°tend `Opus\\Lstsa\\LstsaRunner` pour `mode=memory_batch`.
- Ãƒâ€°tend `Opus\\Lstsa\\LstsaScheduler` avec `enqueueMemoryBatchSmokeRun()`.
- Met ÃƒÂ  jour les scripts CLI Lstsa.
<!-- END MAESTRO_WORKSPACE P112Q2I3_OPUS_Lstsa_BATCH_CHECKPOINT_EXECUTOR -->

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I4_OPUS_Lstsa_REPORTS_ARCHIVES_CATALOG -->
## P112Q2I4_OPUS_Lstsa_REPORTS_ARCHIVES_CATALOG

- CrÃƒÂ©e `Opus\\Lstsa\\LstsaReportCatalog`.
- CrÃƒÂ©e `tools/automation/opus_lstsa_reports.php`.
- CrÃƒÂ©e `bin/opus-lstsa-reports.cmd`.
- CrÃƒÂ©e une recette de validation qui vÃƒÂ©rifie reports/archives/quarantine/checkpoints.
<!-- END MAESTRO_WORKSPACE P112Q2I4_OPUS_Lstsa_REPORTS_ARCHIVES_CATALOG -->

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I5_OPUS_Lstsa_FSM_BACKGROUND_STAGING -->
## P112Q2I5_OPUS_Lstsa_FSM_BACKGROUND_STAGING

### Scope
- Adds explicit FSM control to the Lstsa background runner path.
- Adds phase objects for Load, Secure input, Transform, Secure output, Store, Archive and Report.
- Adds SQLite source/target staging execution through the existing Opus database configuration objects.
- Adds final OK/FAIL events as append-only runtime artifacts.

### Contract
- No heavy HTTP execution.
- The scheduler enqueues, the runner executes, and the FSM controls the authorized next step.
- The target final table is updated only after the staging table is fully validated.
- A failed run removes staging tables and must not leave partial target data.
<!-- END MAESTRO_WORKSPACE P112Q2I5_OPUS_Lstsa_FSM_BACKGROUND_STAGING -->

<!-- BEGIN MAESTRO_WORKSPACE P112Q2J_OPUS_GLOBAL_RECIPE_SUITE -->
## P112Q2J_OPUS_GLOBAL_RECIPE_SUITE

### Scope
- Adds the official global Opus recipe suite under `tools/recipes/`.
- Adds technical recipes for preflight, Git/runtime structure, naming, PHP lint, docs, core, database, FSM, ACL, I18N, routing, template and Lstsa/LSTSAR.
- Adds life robot scenarios simulating anonymous, admin, denied, scheduler, background runner and maintenance flows.
- Adds JSON/Markdown runtime reports under ignored `var/recipes/`.

### Contract
- No HTTP-heavy execution.
- No browser automation.
- No mutation outside dedicated runtime sandboxes.
- New features must register recipes in `RecipeManifest`.
<!-- END MAESTRO_WORKSPACE P112Q2J_OPUS_GLOBAL_RECIPE_SUITE -->


<!-- BEGIN MAESTRO_WORKSPACE P112Q2J2_OPUS_GLOBAL_EVOLUTIVE_HTTP_MAIL_LIFE_RECIPE -->
## P112Q2J2_OPUS_GLOBAL_EVOLUTIVE_HTTP_MAIL_LIFE_RECIPE

### Scope
- Adds `tools/recipes/manifest/opus_feature_manifest.php`.
- Adds `ASAP\Recipe\Recipes\FeatureManifestRecipe`.
- Adds `ASAP\Recipe\Recipes\MailRecipe`.
- Adds `ASAP\Recipe\Life\Scenarios\HttpMailLifeRobotScenario`.
- Adds `tools/recipes/RUN_OPUS_FULL_RECIPE_VISIBLE_BROWSER.cmd`.
- Updates the global recipe manifest and docs recipe.

### Contract
- The visible page must be a rich dashboard, never a blank OK page.
- HTTP checks use real local GET/POST requests.
- Mail is validated through a deterministic sandbox MailRobot inbox.
- LSTSAR is only scheduled through HTTP; execution remains in the background runner.
- New features must be declared in the feature manifest.
<!-- END MAESTRO_WORKSPACE P112Q2J2_OPUS_GLOBAL_EVOLUTIVE_HTTP_MAIL_LIFE_RECIPE -->


## P112Q2J3_OPUS_RECIPE_LIVE_MOVIE_DASHBOARD

### Scope

- Update visible HTTP/mail life recipe dashboard from static page to live movie dashboard.
- Keep existing automatic P112Q2J2 assertions.
- Extend docs and feature manifest.

### Validation

Run:

```cmd
tools\recipes\RUN_OPUS_FULL_RECIPE_VISIBLE_BROWSER.cmd
```

Expected:

```text
OPUS_LIVE_MOVIE_DASHBOARD_OK
OPUS_GLOBAL_RECIPE_OK
```
## P112Q2J4_OPUS_REAL_MAILPIT_LIVE_RECIPE

- Modified `tools/recipes/recipes/MailRecipe.php` to send a real SMTP message to Mailpit and verify it through Mailpit HTTP API.
- Modified `tools/recipes/life/scenarios/HttpMailLifeRobotScenario.php` to use Mailpit SMTP/API instead of a fake JSON inbox.
- Updated docs and feature manifest for real Mailpit life recipe coverage.


## P112Q2K_OPUS_REAL_FEATURES_RECIPE_BINDING

### Scope

- Add `ASAP\Recipe\Recipes\RealFeatureBindingRecipe`.
- Register the recipe in `RecipeManifest` after feature manifest validation.
- Register `real_features_recipe_binding` in `tools/recipes/manifest/opus_feature_manifest.php`.
- Extend docs validation to require the P112Q2K contract.

### Contract

- No sandbox-only success is accepted as a complete Opus anti-regression proof.
- The recipe checks the real `OPUS_REF_BOOK` root and real UwAmp HTTP endpoints.
- The recipe checks the historical Mailpit mail recipe and verifies Mailpit message count growth.
- Missing real routes or missing Mailpit produce explicit failures.

## P112Q2K1_OPUS_AUTOLOADER_CACHE_CONTRACT

Stage-only. Ajoute `AutoloadCache`, `ClassMapBuilder`, recette dÃ©diÃ©e, manifest anti-rÃ©gression, et runner de test sans PowerShell encodÃ©.

## P112Q2L_OPUS_REAL_REFBOOK_HTTP_DIAGNOSTICS

Stage-only. Rend les erreurs HTTP rÃ©elles observables au lieu d'un simple statut 500 opaque.

