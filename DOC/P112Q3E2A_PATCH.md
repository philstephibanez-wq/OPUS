# P112Q3E2A Patch

## Apply

Extraire le ZIP directement dans :

```text
H:\ASAP
```

## Validate

```cmd
cd /d H:\ASAP
tests\Contract\run_refbook_acl_metadata_contract_test.cmd
tools\smoke\run_p112q3e2_refbook_acl_metadata_smoke.cmd
tools\refbook\run_p112q3e2_refbook_acl_metadata_strict.cmd
tools\recipes\run_asap_global_regression_recipe.cmd
tools\recipes\run_p112q3e2_delivery_recipe.cmd
```

## Expected

```text
P112Q3E2_REFBOOK_ACL_METADATA_CONTRACT_UNIT_OK
P112Q3E2_REFBOOK_ACL_METADATA_SMOKE_OK
P112Q3E2_REFBOOK_ACL_METADATA_STRICT_OK
ASAP_GLOBAL_REGRESSION_RECIPE_OK
P112Q3E2_DELIVERY_RECIPE_OK
ExitCode=0
```
