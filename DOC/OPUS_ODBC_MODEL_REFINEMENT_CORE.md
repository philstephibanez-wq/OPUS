# OPUS ODBC Model Refinement Core

Milestone: `P7_ODBC_MODEL_REFINEMENT_CORE`

## Purpose

This milestone refines OPUS Model so ODBC CRUD and the future Model-driven LSTSAR pipeline can consume one shared, explicit field/table write contract.

The Model layer remains database-driver-neutral. ODBC is still the official database boundary for execution.

## Added contracts

- `ModelMutationIntent`
- `ModelFieldProfile`
- `ModelTableIdentity`
- `ModelMutationValidationReport`
- `ModelMutationValidator`
- `ModelWriteProfile`

## Guarantees

- Table identity can be derived from `TableModel` metadata or field native metadata.
- Field profiles expose primary-key, generated, readonly, insertable, updateable and required-on-insert semantics.
- Insert validation rejects generated/read-only fields when they are not insertable.
- Insert validation can require explicit fields when `required` or `required_on_insert` metadata is present.
- Update validation rejects generated/read-only fields when they are not updateable.
- Update and delete validation require a non-empty predicate.
- Predicate validation rejects unknown fields and type/length violations.
- The resulting validation report is explicit and assertable.
- Existing ODBC CRUD commands remain compatible with refined model validation.

## Non-goals

- No SQL execution changes.
- No CRUD UI changes.
- No DDL.
- No LSTSAR restart yet.

Before starting LSTSAR again, stop and explicitly notify the user that ODBC CRUD + Model work is finished.
