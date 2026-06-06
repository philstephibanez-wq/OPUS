# PATCH Ã¢â‚¬â€ P112Q2I1 ASAP Site Multi-DB and LSTSA Contract

## Role
Add the first ASAP contract layer for site multi-database declarations and LSTSA.

## Added
- `ASAP\Database\DatabaseConnectionsConfig`
- `ASAP\Database\DatabaseMultiConfigLoader`
- `ASAP\LSTSA\LstsaException`
- `ASAP\LSTSA\LstsaFieldConstraint`
- `ASAP\LSTSA\LstsaFieldMapping`
- `ASAP\LSTSA\LstsaDefinition`
- `ASAP\LSTSA\LstsaConfigLoader`
- `ASAP\LSTSA\LstsaReport`
- `ASAP\LSTSA\LstsaArchiveWriter`
- LSTSA smoke recipe and automation check.

## Contract
LSTSA means Load / Secure / Transform / Store / Archive.
Input and output field constraints include type, required, length, byte size, enum, regex and numeric bounds.
Reports are JSON + Markdown and archive writing is append-only.

## Not done here
No long runner is started from Apache. The runner/scheduler must be introduced in the next palier.

## Next
`P112Q2I2_ASAP_LSTSA_RUNNER_SCHEDULER_FOUNDATION`

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I2_ASAP_LSTSA_RUNNER_SCHEDULER_BASELINE -->
## P112Q2I2_ASAP_LSTSA_RUNNER_SCHEDULER_BASELINE

- CrÃ©e `ASAP\LSTSA\LstsaRunStatus`.
- CrÃ©e `ASAP\LSTSA\LstsaRunStore`.
- CrÃ©e `ASAP\LSTSA\LstsaScheduler`.
- CrÃ©e `ASAP\LSTSA\LstsaRunner`.
- CrÃ©e `bin/asap-lstsa-runner.cmd` et `bin/asap-lstsa-scheduler.cmd`.
- Ajoute ignores runtime queue/locks/heartbeats.
<!-- END MAESTRO_WORKSPACE P112Q2I2_ASAP_LSTSA_RUNNER_SCHEDULER_BASELINE -->

<!-- BEGIN MAESTRO_WORKSPACE P112Q2I3_ASAP_LSTSA_BATCH_CHECKPOINT_EXECUTOR -->
## P112Q2I3_ASAP_LSTSA_BATCH_CHECKPOINT_EXECUTOR

- Ajoute `ASAP\\LSTSA\\LstsaBatchExecutor`.
- Ã‰tend `ASAP\\LSTSA\\LstsaRunStore` avec checkpoints/archives/quarantine.
- Ã‰tend `ASAP\\LSTSA\\LstsaRunner` pour `mode=memory_batch`.
- Ã‰tend `ASAP\\LSTSA\\LstsaScheduler` avec `enqueueMemoryBatchSmokeRun()`.
- Met Ã  jour les scripts CLI LSTSA.
<!-- END MAESTRO_WORKSPACE P112Q2I3_ASAP_LSTSA_BATCH_CHECKPOINT_EXECUTOR -->

