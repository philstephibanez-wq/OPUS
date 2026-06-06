# P112Q2I3_ASAP_Lstsa_BATCH_CHECKPOINT_EXECUTOR

## Objectif

Ajouter le premier exécuteur Lstsa en batch avec checkpoints, contrôles input/output, quarantine et rapports/archives runtime append-only, sans faire tourner un traitement long dans Apache ou dans une requête HTTP.

## Périmètre

- Extension de `ASAP\Lstsa\LstsaRunStore` avec artifacts runtime : checkpoints, archives, quarantine.
- Ajout de `ASAP\Lstsa\LstsaBatchExecutor`.
- Extension de `ASAP\Lstsa\LstsaRunner` pour traiter les runs `mode=memory_batch`.
- Extension de `ASAP\Lstsa\LstsaScheduler` avec une recette smoke batch.
- Mise à jour des scripts CLI scheduler/runner.
- Recette automatique `p112q2i3_lstsa_batch_checkpoint_executor_recipe.php`.

## Contrat validé

Un run Lstsa batch doit :

1. Charger un lot de lignes.
2. Valider les champs source déclarés.
3. Refuser les champs inconnus.
4. Appliquer uniquement les transformations allowlistées.
5. Valider les champs cible après transformation.
6. Stocker seulement les lignes valides.
7. Créer un checkpoint par batch.
8. Écrire une archive runtime append-only des lignes stockées.
9. Écrire une quarantine runtime append-only des lignes rejetées.
10. Produire un rapport JSON/MD incluant les artifacts.

## Hors périmètre

- Pas encore d'écriture réelle dans SQLite/MySQL.
- Pas encore d'UI ASAP pour consulter les runs.
- Pas encore de reprise automatique depuis checkpoint après crash.

## Prochain palier prévu

`P112Q2I4_ASAP_Lstsa_DB_STORE_PROVIDER` : brancher le store sur les providers multi-BDD, avec transaction, insert/append/update/upsert contractuels.
