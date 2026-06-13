# TODO — OPUS

## Validate now

- Run `php tools\smoke_p116b_score_template_final.php`.
- Run `tools\recipes\RUN_OPUS_FULL_RECIPE.cmd` when local runtime dependencies are ready.
- Pull the P116B branch locally and verify OPUS_REF_BOOK still works before merging application migration.

## Next chantier

`P116C_OPUS_REF_BOOK_SCORE_TEMPLATE_MIGRATION`

## ScoreTemplate migration rules

- Migrate consuming applications from `.twig` to `.score` progressively.
- Do not remove `TwigTemplateRenderer` until OPUS_REF_BOOK and active applications no longer instantiate it.
- Do not reintroduce Smarty, x64 or `Opus\Template\Adapter`.
- Keep templates representation-only: no PHP, no service calls, no data loading.
- Add a focused smoke for each migrated application page.
