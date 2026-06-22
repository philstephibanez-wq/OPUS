# OPUS P1B — View wired to ScoreTemplate layout

## Objectif

Aligner `Opus/View.php` avec le moteur `ScoreTemplateRenderer` sans refonte métier.

Ce palier corrige le point signalé par `P1_OPUS_BOOT_RENDER_SMOKE_FAIL` :

```text
CHECK_VIEW_SCORE_TEMPLATE_INTEGRATION=FAIL Opus/View.php does not reference ScoreTemplateRenderer yet.
```

## Scope

Modifié :

```text
Opus/View.php
Opus/Score/templates/view/layout.score
tools/smoke_opus_view_scoretemplate_p1b.php
```

Non modifié :

```text
sites/
vendor/
var/cache/
FSM legacy
Router legacy
classes OPUS_* legacy
```

## Contrat

- `View.php` reste la façade simple de rendu HTML.
- Le layout HTML complet est maintenant un template `.score`.
- Les fragments déjà construits par `View.php` sont transmis au layout comme données de vue.
- Le rendu du layout passe explicitement par `Opus\Template\ScoreTemplateRenderer`.
- Aucun fallback de template n'est introduit.

## Smoke local

```cmd
cd /d H:\OPUS
git pull --ff-only
php tools\smoke_opus_boot_render_p1.php
php tools\smoke_opus_view_scoretemplate_p1b.php
git status --short
```

Résultats attendus :

```text
P1_OPUS_BOOT_RENDER_SMOKE_OK
P1B_OPUS_VIEW_SCORETEMPLATE_SMOKE_OK
```
