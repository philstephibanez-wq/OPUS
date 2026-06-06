# P112Q2I4_ASAP_Lstsa_REPORTS_ARCHIVES_CATALOG

## Objectif

Consolider la couche de consultation des rapports et archives Lstsa sans exécuter de travail long dans Apache.

## Contrat

- Le concept métier reste écrit `Lstsa` dans les docs, rapports et messages utilisateur.
- Les symboles PHP restent alignés avec la convention ASAP : `LstsaReportCatalog`, `LstsaRunner`, `LstsaScheduler`.
- Le catalogue lit les runs existants sous `var/lstsa/queue`.
- Le catalogue vérifie la présence des rapports JSON/MD, archives, quarantaines et checkpoints déclarés.
- Le catalogue écrit des snapshots JSON/MD append-only sous `var/lstsa/reports/_index`.
- Aucun catalogue runtime n'est versionné dans Git.

## Fichiers ajoutés

- `framework/Asap/Lstsa/LstsaReportCatalog.php`
- `tools/automation/asap_lstsa_reports.php`
- `bin/asap-lstsa-reports.cmd`
- `tools/automation/p112q2i4_lstsa_reports_archives_catalog_recipe.php`
- `tools/automation/p112q2i4_lstsa_reports_archives_catalog_check.cmd`

## Validation

La recette crée un run Lstsa memory batch, l'exécute via runner CLI, génère un catalogue, puis vérifie :

- rapport JSON présent,
- rapport Markdown présent,
- archive présente,
- quarantine présente,
- checkpoints présents,
- aucun artifact manquant.

## Prochain palier

P112Q2I5 : préparer une exposition lisible côté Reference Book / backoffice sans lancer les traitements Lstsa dans le cycle HTTP.
