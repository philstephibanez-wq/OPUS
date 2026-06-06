# P112Q2I1 — ASAP Site Multi-DB and Lstsa Contract

## Role
Introduce the first official ASAP foundation for:

- site-level multi-database declarations;
- Lstsa contracts;
- strict input/output constraints;
- append-only reports and archives.

## Lstsa meaning

```text
Load / Secure / Transform / Store / Archive
```

The archive step includes machine-readable JSON reports and readable Markdown reports.

## Contract
- A site may declare several named database connections.
- Each database connection must have an explicit name and provider.
- Lstsa must declare its source connection/table and target connection/table.
- Lstsa must check source fields before transformation.
- Lstsa must check target fields after transformation.
- Lstsa supports length and byte constraints as first-class validation rules.
- Lstsa transformation names are declarations only and must later be resolved through an allowlisted registry.
- Lstsa archives are append-only.
- Lstsa runtime outputs remain outside Git by default.

## New public namespaces

```text
ASAP\Database\DatabaseConnectionsConfig
ASAP\Database\DatabaseMultiConfigLoader
ASAP\Lstsa\*
```

## Runtime warning
This palier does not execute long Lstsa jobs through HTTP. The next palier must introduce a CLI runner and scheduler so long jobs do not depend on Apache, browser or PHP request timeouts.

## Validation
The palier is valid when:

```text
P112Q2I1_SITE_MULTI_DB_AND_Lstsa_CONTRACT_RECIPE_OK
P112Q2I1_TEST_OK
```
