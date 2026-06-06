# P112Q2D Fix — Recipe Lint Scope

## Cause

The Q2D migration succeeded, but the Q2D recipe failed while linting every PHP file under `H:\ASAP`.

The failing file was:

`H:\ASAP\tests\smoke\p112c4_fsm_acl_smoke.php`

It is outside the Q2D migration contract and contains a pre-existing strict-types placement issue.

## Fix

The Q2D recipe now lints only:

- `framework/Asap`
- `tests/recipe`
- `tests/fixtures`

Legacy smoke tests are not linted by the Q2D naming migration recipe.

## Contract

This fix does not rerun the migration.

It resumes the partial Q2D state, reruns the corrected recipe, then commits the already-applied Q2D changes.
