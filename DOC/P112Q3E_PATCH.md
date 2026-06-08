# P112Q3E PATCH

## Scope

Repository: ASAP

## Files added

- `framework/Asap/RefBook/Attribute/AsapRefBookClass.php`
- `framework/Asap/RefBook/Attribute/AsapRefBookMethod.php`
- `framework/Asap/RefBook/Contract/RefBookInspectableInterface.php`
- `framework/Asap/RefBook/Model/RefBookClassEntry.php`
- `framework/Asap/RefBook/Model/RefBookMethodEntry.php`
- `framework/Asap/RefBook/Model/RefBookScanResult.php`
- `framework/Asap/RefBook/RefBookReflectionScanner.php`
- `framework/Asap/RefBook/RefBookContractValidator.php`
- `framework/Asap/RefBook/RefBookSnapshotBuilder.php`
- `tests/fixtures/refbook/P112Q3ERefBookFixtureService.php`
- `tests/Contract/RefBookReflectionContractTest.php`
- `tools/refbook/p112q3e_refbook_reflection_contract.php`
- `tools/recipes/asap_global_regression_recipe.php`
- `tools/recipes/p112q3e_delivery_recipe.php`
- `.cmd` wrappers

## Files updated

- `.vscode/tasks.json`

## Runtime behavior

No existing framework runtime path is changed.

The patch adds a new RefBook Reflection/snapshot subsystem and recipe commands.
