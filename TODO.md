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
- [ ] P112Q2I5 : exposition Reference Book / backoffice, sans exÃ©cution HTTP.
<!-- END MAESTRO_WORKSPACE P112Q2I4_ASAP_Lstsa_REPORTS_ARCHIVES_CATALOG -->

