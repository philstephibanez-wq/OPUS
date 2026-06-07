# TODO Ã¢â‚¬â€ P112Q2I1 ASAP Site Multi-DB and Lstsa Contract

## Validate now
- Run `TEST_P112Q2I1_ASAP_SITE_MULTI_DB_AND_Lstsa_CONTRACT.cmd`.
- Push the new commit to GitHub after validation.

## Next chantier
`P112Q2I2_ASAP_Lstsa_RUNNER_SCHEDULER_FOUNDATION`

## Runner rules
- Long Lstsa jobs must run outside HTTP.
- Use CLI runner + scheduler.
- Add queue, lock, heartbeat and stale detection.
- Reports and archives remain mandatory.

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I2_ASAP_Lstsa_RUNNER_SCHEDULER_BASELINE -->
## P112Q2I2_ASAP_Lstsa_RUNNER_SCHEDULER_BASELINE

- [x] Runner CLI baseline hors timeout HTTP.
- [x] Scheduler baseline.
- [x] Queue fichier locale.
- [x] Lock anti double exÃ©cution.
- [x] Heartbeat par Ã©tape.
- [ ] P112Q2I3 : brancher le runner sur les dÃ©finitions Lstsa rÃ©elles et les providers multi-BDD.
<!-- END MAESTRO_WORKSPACE P112Q2I2_ASAP_Lstsa_RUNNER_SCHEDULER_BASELINE -->

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I3_ASAP_Lstsa_BATCH_CHECKPOINT_EXECUTOR -->
## P112Q2I3_ASAP_Lstsa_BATCH_CHECKPOINT_EXECUTOR

- [x] ExÃ©cution batch hors HTTP.
- [x] Checkpoint par batch.
- [x] Secure input avant transform.
- [x] Secure output aprÃ¨s transform.
- [x] Archive append-only runtime.
- [x] Quarantine runtime.
- [ ] P112Q2I4 : store rÃ©el via providers multi-BDD.
<!-- END MAESTRO_WORKSPACE P112Q2I3_ASAP_Lstsa_BATCH_CHECKPOINT_EXECUTOR -->

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I4_ASAP_Lstsa_REPORTS_ARCHIVES_CATALOG -->
## P112Q2I4_ASAP_Lstsa_REPORTS_ARCHIVES_CATALOG

- [x] Cataloguer les runs Lstsa.
- [x] VÃ©rifier rapports JSON/MD.
- [x] VÃ©rifier archives runtime.
- [x] VÃ©rifier quarantine et checkpoints.
- [x] Conserver `Lstsa*` pour les symboles PHP.
- [x] P112Q2I5 : controle FSM background + staging SQLite cible, sans execution HTTP.
<!-- END MAESTRO_WORKSPACE P112Q2I4_ASAP_Lstsa_REPORTS_ARCHIVES_CATALOG -->

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I5_ASAP_Lstsa_FSM_BACKGROUND_STAGING -->
## P112Q2I5_ASAP_Lstsa_FSM_BACKGROUND_STAGING

- [x] Runner background piloté par FSM.
- [x] Objets de phase Load/Secure/Transform/Store/Archive/Report.
- [x] Store via table de staging contrôlée dans la BDD cible.
- [x] Commit final cible uniquement après validation 100 %.
- [x] Cleanup staging en succès et échec.
- [x] Event OK / FAIL append-only.
- [ ] P112Q2I6 : durcir le mapping SQL multi-provider au-delà du smoke SQLite.
<!-- END MAESTRO_WORKSPACE P112Q2I5_ASAP_Lstsa_FSM_BACKGROUND_STAGING -->

<!-- BEGIN MAESTRO_WORKSPACE P112Q2J_ASAP_GLOBAL_RECIPE_SUITE -->
## P112Q2J_ASAP_GLOBAL_RECIPE_SUITE

- [x] Créer la suite globale de recette ASAP.
- [x] Ajouter un manifest de recettes évolutif.
- [x] Couvrir les recettes techniques principales.
- [x] Ajouter des scénarios life robotisés multi-acteurs.
- [x] Produire rapports JSON/Markdown runtime ignorés.
- [ ] P112Q2J1 : brancher chaque futur palier à une recette obligatoire dans le manifest global.
<!-- END MAESTRO_WORKSPACE P112Q2J_ASAP_GLOBAL_RECIPE_SUITE -->


<!-- BEGIN MAESTRO_WORKSPACE P112Q2J2_ASAP_GLOBAL_EVOLUTIVE_HTTP_MAIL_LIFE_RECIPE -->
## P112Q2J2_ASAP_GLOBAL_EVOLUTIVE_HTTP_MAIL_LIFE_RECIPE

- [x] Ajouter un manifest anti-régression des fonctionnalités ASAP.
- [x] Ajouter une recette mail technique.
- [x] Ajouter une recette HTTP life avec vraie page dashboard visible.
- [x] Ajouter un MailRobot send/receive sandboxé.
- [x] Tester GET/POST, ACL, I18N, formulaire et LSTSAR via HTTP local.
- [ ] Brancher plus tard un transport Mailpit réel si le projet décide de l'exiger comme dépendance locale.
<!-- END MAESTRO_WORKSPACE P112Q2J2_ASAP_GLOBAL_EVOLUTIVE_HTTP_MAIL_LIFE_RECIPE -->


## P112Q2J3 follow-up

- Later connect the movie dashboard to real production ASAP pages when the front routing contract is stable.
- Add screenshots/video capture only after the dashboard contract is stable.
## P112Q2J4 follow-up

- Keep Mailpit ports configurable by environment variables for future non-default setups.
- Extend visible dashboard with screenshots/Panther only after legacy browser recipe inventory is restored.


## P112Q2K follow-up

- Keep real feature URL list synchronized with the ASAP_REF_BOOK historical pages.
- Add screenshot/Panther verification only after the real feature binding remains stable.
- Do not validate future visual recipes without real feature binding passing first.

## P112Q2K1_ASAP_AUTOLOADER_CACHE_CONTRACT

- Après validation, relancer P112Q2K pour diagnostiquer la panne réelle `ASAP_REF_BOOK` avec un autoload/cache propre.

