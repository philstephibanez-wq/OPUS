# PATCH — P116B ScoreTemplate native final contract

## Role

Make ScoreTemplate the native Opus template contract and remove legacy adapter expectations from the template validation path.

## Added

- `tools/smoke_p116b_score_template_final.php`
- `DOC/P116B_SCORETEMPLATE_NATIVE_CONTRACT.md`

## Modified

- `Opus\Template\ScoreTemplateRenderer`
- `tools/recipes/recipes/TemplateRecipe.php`
- `README.md`
- `CHANGELOG.md`
- `TODO.md`

## Removed

- `Opus\Template\Adapter` as an official template target.

## Contract

ScoreTemplate renders ViewModel data only. It must not execute PHP, read business data, call services, route requests, decide permissions, or fall back to Twig, Smarty, x64 or a legacy adapter.

Twig remains temporary only for application migration. Smarty and x64 are not Opus template targets.

## Validation

Run:

```cmd
php tools\smoke_p116b_score_template_final.php
tools\recipes\RUN_OPUS_FULL_RECIPE.cmd
```
