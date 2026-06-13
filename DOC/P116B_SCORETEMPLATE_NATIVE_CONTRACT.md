# P116B — ScoreTemplate native final contract

## Role

ScoreTemplate is the native Opus template engine. It is the final framework target for application rendering, replacing legacy template adapters and preparing the future removal of Twig, Smarty and x64.

## Scope

This palier strengthens `Opus\Template\ScoreTemplateRenderer` with the ScoreTemplate v1 contract:

- escaped interpolation with `{{ path.to.value }}`;
- explicit raw interpolation with `{{{ path.to.html }}}`;
- controlled includes with `[[ include:partials/name.score ]]`;
- simple conditions with `[[ if: condition ]]`, `[[ else ]]`, `[[ endif ]]`;
- simple loops with `[[ foreach: items as item ]]` and key/value loops;
- loop metadata through `loop.index`, `loop.index0`, `loop.first`, `loop.last`, `loop.length`;
- whitelist filters: `upper`, `lower`, `trim`, `default`, `date`;
- explicit failures for missing data, unknown directives, forbidden paths and PHP tags.

## Contract

ScoreTemplate represents only. It does not:

- execute PHP from templates;
- call services;
- read business files;
- access databases;
- route requests;
- decide permissions;
- silently fall back to Twig, Smarty, x64 or any adapter.

The template renderer consumes prepared ViewModel data only.

## Legacy removal

`Opus\Template\Adapter` is removed from the official template recipe. Smarty and x64 are not valid Opus template targets. Twig remains only as a temporary migration renderer until consuming applications are converted to `.score` templates.

## Validation

Run the focused smoke:

```cmd
php tools\smoke_p116b_score_template_final.php
```

Then run the global recipe when the local workspace is ready:

```cmd
tools\recipes\RUN_OPUS_FULL_RECIPE.cmd
```

Expected markers:

- `P116B_SCORE_TEMPLATE_FINAL_SMOKE_OK`
- `OPUS_SCORE_TEMPLATE_OK`
- `OPUS_GLOBAL_RECIPE_OK`
