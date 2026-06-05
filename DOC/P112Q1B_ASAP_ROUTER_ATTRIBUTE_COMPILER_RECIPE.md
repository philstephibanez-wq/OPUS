# P112Q1B — Router Attribute Compiler Recipe

## Objectif

Ajouter la recette officielle du contrat P112Q1.

## Vérifications

- présence des fichiers runtime Router/Attribute/Compiler
- présence des docs ASAP
- présence de la page Reference Book
- ClassIndex déterministe
- lecture des attributs PHP8 `#[Route]`
- tri par priorité
- métadonnées `acl`, `format`, `source`
- compilation du manifest
- écriture/lecture du manifest PHP
- détection bloquante des conflits `path + method`

## Commande

`H:\ASAP\tools\automation\p112q1_router_attribute_compiler_recipe_runner.cmd`

## Contrat

La recette ne compile pas les routes pendant l'autoload. Elle appelle explicitement le provider et le compiler.

Aucun redémarrage Apache.
