# TODO ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â P112Q2I1 Opus Site Multi-DB and Lstsa Contract

## Validate now
- Run `TEST_P112Q2I1_OPUS_SITE_MULTI_DB_AND_Lstsa_CONTRACT.cmd`.
- Push the new commit to GitHub after validation.

## Next chantier
`P112Q2I2_OPUS_Lstsa_RUNNER_SCHEDULER_FOUNDATION`

## Runner rules
- Long Lstsa jobs must run outside HTTP.
- Use CLI runner + scheduler.
- Add queue, lock, heartbeat and stale detection.
- Reports and archives remain mandatory.

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I2_OPUS_Lstsa_RUNNER_SCHEDULER_BASELINE -->
## P112Q2I2_OPUS_Lstsa_RUNNER_SCHEDULER_BASELINE

- [x] Runner CLI baseline hors timeout HTTP.
- [x] Scheduler baseline.
- [x] Queue fichier locale.
- [x] Lock anti double exÃƒÂ©cution.
- [x] Heartbeat par ÃƒÂ©tape.
- [ ] P112Q2I3 : brancher le runner sur les dÃƒÂ©finitions Lstsa rÃƒÂ©elles et les providers multi-BDD.
<!-- END MAESTRO_WORKSPACE P112Q2I2_OPUS_Lstsa_RUNNER_SCHEDULER_BASELINE -->

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I3_OPUS_Lstsa_BATCH_CHECKPOINT_EXECUTOR -->
## P112Q2I3_OPUS_Lstsa_BATCH_CHECKPOINT_EXECUTOR

- [x] ExÃƒÂ©cution batch hors HTTP.
- [x] Checkpoint par batch.
- [x] Secure input avant transform.
- [x] Secure output aprÃƒÂ¨s transform.
- [x] Archive append-only runtime.
- [x] Quarantine runtime.
- [ ] P112Q2I4 : store rÃƒÂ©el via providers multi-BDD.
<!-- END MAESTRO_WORKSPACE P112Q2I3_OPUS_Lstsa_BATCH_CHECKPOINT_EXECUTOR -->

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I4_OPUS_Lstsa_REPORTS_ARCHIVES_CATALOG -->
## P112Q2I4_OPUS_Lstsa_REPORTS_ARCHIVES_CATALOG

- [x] Cataloguer les runs Lstsa.
- [x] VÃƒÂ©rifier rapports JSON/MD.
- [x] VÃƒÂ©rifier archives runtime.
- [x] VÃƒÂ©rifier quarantine et checkpoints.
- [x] Conserver `Lstsa*` pour les symboles PHP.
- [x] P112Q2I5 : controle FSM background + staging SQLite cible, sans execution HTTP.
<!-- END MAESTRO_WORKSPACE P112Q2I4_OPUS_Lstsa_REPORTS_ARCHIVES_CATALOG -->

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I5_OPUS_Lstsa_FSM_BACKGROUND_STAGING -->
## P112Q2I5_OPUS_Lstsa_FSM_BACKGROUND_STAGING

- [x] Runner background pilotÃ© par FSM.
- [x] Objets de phase Load/Secure/Transform/Store/Archive/Report.
- [x] Store via table de staging contrÃ´lÃ©e dans la BDD cible.
- [x] Commit final cible uniquement aprÃ¨s validation 100 %.
- [x] Cleanup staging en succÃ¨s et Ã©chec.
- [x] Event OK / FAIL append-only.
- [ ] P112Q2I6 : durcir le mapping SQL multi-provider au-delÃ  du smoke SQLite.
<!-- END MAESTRO_WORKSPACE P112Q2I5_OPUS_Lstsa_FSM_BACKGROUND_STAGING -->

<!-- BEGIN MAESTRO_WORKSPACE P112Q2J_OPUS_GLOBAL_RECIPE_SUITE -->
## P112Q2J_OPUS_GLOBAL_RECIPE_SUITE

- [x] CrÃ©er la suite globale de recette Opus.
- [x] Ajouter un manifest de recettes Ã©volutif.
- [x] Couvrir les recettes techniques principales.
- [x] Ajouter des scÃ©narios life robotisÃ©s multi-acteurs.
- [x] Produire rapports JSON/Markdown runtime ignorÃ©s.
- [ ] P112Q2J1 : brancher chaque futur palier Ã  une recette obligatoire dans le manifest global.
<!-- END MAESTRO_WORKSPACE P112Q2J_OPUS_GLOBAL_RECIPE_SUITE -->


<!-- BEGIN MAESTRO_WORKSPACE P112Q2J2_OPUS_GLOBAL_EVOLUTIVE_HTTP_MAIL_LIFE_RECIPE -->
## P112Q2J2_OPUS_GLOBAL_EVOLUTIVE_HTTP_MAIL_LIFE_RECIPE

- [x] Ajouter un manifest anti-rÃ©gression des fonctionnalitÃ©s Opus.
- [x] Ajouter une recette mail technique.
- [x] Ajouter une recette HTTP life avec vraie page dashboard visible.
- [x] Ajouter un MailRobot send/receive sandboxÃ©.
- [x] Tester GET/POST, ACL, I18N, formulaire et LSTSAR via HTTP local.
- [ ] Brancher plus tard un transport Mailpit rÃ©el si le projet dÃ©cide de l'exiger comme dÃ©pendance locale.
<!-- END MAESTRO_WORKSPACE P112Q2J2_OPUS_GLOBAL_EVOLUTIVE_HTTP_MAIL_LIFE_RECIPE -->


## P112Q2J3 follow-up

- Later connect the movie dashboard to real production Opus pages when the front routing contract is stable.
- Add screenshots/video capture only after the dashboard contract is stable.
## P112Q2J4 follow-up

- Keep Mailpit ports configurable by environment variables for future non-default setups.
- Extend visible dashboard with screenshots/Panther only after legacy browser recipe inventory is restored.


## P112Q2K follow-up

- Keep real feature URL list synchronized with the OPUS_REF_BOOK historical pages.
- Add screenshot/Panther verification only after the real feature binding remains stable.
- Do not validate future visual recipes without real feature binding passing first.

## P112Q2K1_OPUS_AUTOLOADER_CACHE_CONTRACT

- AprÃ¨s validation, relancer P112Q2K pour diagnostiquer la panne rÃ©elle `OPUS_REF_BOOK` avec un autoload/cache propre.

## P112Q2L_OPUS_REAL_REFBOOK_HTTP_DIAGNOSTICS

- AprÃ¨s validation, conserver les diagnostics HTTP comme contrat de recette rÃ©elle pour les futures Ã©volutions RefBook.

