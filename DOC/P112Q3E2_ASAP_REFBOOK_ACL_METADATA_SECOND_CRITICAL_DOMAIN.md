# P112Q3E2 — ASAP RefBook ACL Metadata Second Critical Domain

## Scope

This delivery extends the Reflection + RefBook Attribute contract to the ACL domain.

## Contract

- `Reflection` remains the source of technical truth: classes, public methods, parameters and return types.
- `AsapRefBookClass` and `AsapRefBookMethod` provide functional documentation metadata.
- The ACL domain must expose a strict, observable report with zero class/method metadata violations.
- The global ASAP regression recipe is mandatory after the targeted feature tests.

## Domain covered

`framework/Asap/Acl`

Expected baseline after this delivery:

- Classes/interfaces: 9
- Public methods: 28
- Class metadata missing: 0
- Method metadata missing: 0
- Violations: 0

## Commands

```cmd
cd /d H:\ASAP
tests\Contract\run_refbook_acl_metadata_contract_test.cmd
tools\smoke\run_p112q3e2_refbook_acl_metadata_smoke.cmd
tools\refbook\run_p112q3e2_refbook_acl_metadata_strict.cmd
tools\recipes\run_asap_global_regression_recipe.cmd
tools\recipes\run_p112q3e2_delivery_recipe.cmd
```

## Expected markers

```text
P112Q3E2_REFBOOK_ACL_METADATA_CONTRACT_UNIT_OK
P112Q3E2_REFBOOK_ACL_METADATA_SMOKE_OK
P112Q3E2_REFBOOK_ACL_METADATA_STRICT_OK
ASAP_GLOBAL_REGRESSION_RECIPE_OK
P112Q3E2_DELIVERY_RECIPE_OK
ExitCode=0
```
