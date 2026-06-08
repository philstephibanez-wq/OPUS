# P112Q3B1 — ASAP Panther Recipe Detection and VS Code Tasks

## Rôle

Corriger le smoke P112Q3B et rendre la recette Panther plus observable depuis ASAP.

## Correction

Le smoke P112Q3B précédent cherchait le nom de classe Panther sous une forme texte trop stricte dans le fichier recette. Le code PHP contenait la chaîne échappée `Symfony\\Component\\Panther\\Client`, tandis que le smoke comparait une représentation runtime. Le résultat était un faux échec `P112Q3B_PANTHER_RECIPE_MARKER_MISSING`.

P112Q3B1 remplace cette assertion par des marqueurs de contrat plus robustes :

- `PANTHER_CLIENT_NOT_AVAILABLE`
- `class_exists`
- `ASAP_P112Q3B_PANTHER_AUTOLOAD`

## Panther explicite

La recette ne tente aucune installation automatique. Elle charge :

1. `H:\ASAP\vendor\autoload.php` par défaut ;
2. ou le chemin explicite `ASAP_P112Q3B_PANTHER_AUTOLOAD` si Panther est porté par un autre autoload Composer.

## VS Code

Ajout de `.vscode/tasks.json` avec :

- `ASAP · Smoke P112Q3B Secure Dispatch Gate`
- `ASAP · Recipe P112Q3B Panther`
- `ASAP · Recipe P112Q3B Panther Required`

## Contrat

- Aucun changement framework.
- Aucun changement Router/FSM/ACL.
- Aucun changement Apache/UwAmp/BDD.
- Aucun install Composer automatique.
- Panther absent = `SKIPPED` explicite par défaut.
- Panther absent avec `ASAP_P112Q3B_PANTHER_REQUIRED=1` = `FAILED` explicite.
