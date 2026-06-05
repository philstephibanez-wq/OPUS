# P112Q2I1 — ASAP Site Multi-DB and LSTSA Contract

## Role
Introduce the first official ASAP foundation for:

- site-level multi-database declarations;
- LSTSA contracts;
- strict input/output constraints;
- append-only reports and archives.

## LSTSA meaning

```text
Load / Secure / Transform / Store / Archive
```

The archive step includes machine-readable JSON reports and readable Markdown reports.

## Contract
- A site may declare several named database connections.
- Each database connection must have an explicit name and provider.
- LSTSA must declare its source connection/table and target connection/table.
- LSTSA must check source fields before transformation.
- LSTSA must check target fields after transformation.
- LSTSA supports length and byte constraints as first-class validation rules.
- LSTSA transformation names are declarations only and must later be resolved through an allowlisted registry.
- LSTSA archives are append-only.
- LSTSA runtime outputs remain outside Git by default.

## New public namespaces

```text
ASAP\Database\DatabaseConnectionsConfig
ASAP\Database\DatabaseMultiConfigLoader
ASAP\LSTSA\*
```

## Runtime warning
This palier does not execute long LSTSA jobs through HTTP. The next palier must introduce a CLI runner and scheduler so long jobs do not depend on Apache, browser or PHP request timeouts.

## Validation
The palier is valid when:

```text
P112Q2I1_SITE_MULTI_DB_AND_LSTSA_CONTRACT_RECIPE_OK
P112Q2I1_TEST_OK
```
