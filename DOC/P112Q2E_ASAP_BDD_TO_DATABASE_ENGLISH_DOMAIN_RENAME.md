# P112Q2E — ASAP BDD to Database English Domain Rename

## Purpose

P112Q2E closes the final naming finding after P112Q2D.

It renames the French technical abbreviation `BDD` to the pure English domain name `Database`.

## Scope

- `framework/Asap/BDD` -> `framework/Asap/Database`
- `ASAP\BDD` -> `ASAP\Database`

## Why it was isolated

Unlike previous directory-case steps, this is a semantic English domain rename.

It changes a French technical abbreviation into the framework English domain vocabulary.

## Contract

- No fallback directory.
- No legacy namespace alias.
- No runtime autoload magic.
- Runtime namespace/path references are updated.
- Q2D, Q2C, Q2B1, and Q1 recipes are rerun as regression checks.

## Runner

`H:\ASAP\tools\automation\p112q2e_bdd_to_database_english_domain_rename_recipe_runner.cmd`
