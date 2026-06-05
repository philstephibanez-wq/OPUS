# PATCH â€” P112Q2I1 ASAP Site Multi-DB and LSTSA Contract

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