# OPUS P1 — Boot and render smoke

## Objectif

Valider le socle runtime minimal apres `P0_OPUS_REBORN_CLEANUP`.

Ce palier ne modifie pas le framework. Il verifie seulement :

- le chargement de `Opus/Bootstrap.php` ;
- le chargement des classes core `Opus` ;
- le rendu natif `ScoreTemplateRenderer` sur un template temporaire isole ;
- l'integration de `Opus/View.php` avec `ScoreTemplateRenderer`.

## Commande

```cmd
cd /d H:\OPUS
git pull --ff-only
php tools\smoke_opus_boot_render_p1.php
```

## Resultat attendu a terme

```text
P1_OPUS_BOOT_RENDER_SMOKE_OK
```

## Etat connu au debut de P1

Le moteur `ScoreTemplateRenderer` existe et sait rendre un template `.score` de maniere native.

Le test peut encore signaler :

```text
CHECK_VIEW_SCORE_TEMPLATE_INTEGRATION=FAIL
```

si `Opus/View.php` ne reference pas encore `ScoreTemplateRenderer`.

Ce n'est pas un rollback : c'est le verrou qui identifie le prochain micro-palier.

## Hors scope

- pas de refonte MVC ;
- pas de changement FSM ;
- pas de sortie des sites du depot ;
- pas de migration composer avancee ;
- pas de modification de `sites/`, `vendor/`, `var/cache/`, `var/log/`, `var/tmp/`.
