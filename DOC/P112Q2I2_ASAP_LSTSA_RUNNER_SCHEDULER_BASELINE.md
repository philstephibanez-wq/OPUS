# P112Q2I2 — ASAP LSTSA Runner / Scheduler baseline

## Objectif

Ce palier ajoute le socle d'exécution LSTSA hors requête HTTP.

Le site ASAP peut déclencher et consulter des runs, mais les transformations longues ne doivent pas dépendre d'Apache, du navigateur, ni du timeout PHP web.

## Contrat

LSTSA signifie :

```text
Load
Secure
Transform
Store
Archive
```

À partir de ce palier, l'exécution se fait par un runner CLI.

## Fichiers ajoutés

```text
framework/ASAP/LSTSA/LstsaRunStatus.php
framework/ASAP/LSTSA/LstsaRunStore.php
framework/ASAP/LSTSA/LstsaScheduler.php
framework/ASAP/LSTSA/LstsaRunner.php
tools/automation/asap_lstsa_scheduler.php
tools/automation/asap_lstsa_runner.php
tools/automation/p112q2i2_lstsa_runner_scheduler_baseline_recipe.php
bin/asap-lstsa-runner.cmd
bin/asap-lstsa-scheduler.cmd
```

## Runtime hors Git

```text
var/lstsa/queue/
var/lstsa/locks/
var/lstsa/heartbeats/
var/lstsa/reports/
var/lstsa/archives/
var/lstsa/quarantine/
```

Ces dossiers sont runtime et ne doivent pas entrer dans Git.

## Statuts supportés

```text
PENDING
QUEUED
RUNNING
PAUSED
DONE
PARTIAL
FAILED
CANCELLED
TIMEOUT_EXCEEDED
QUARANTINED
```

## Baseline volontairement limitée

Ce palier ne fait pas encore de vraie transformation multi-BDD.

Il valide :

```text
schedule -> queue -> lock -> run -> heartbeat -> report
```

La connexion aux définitions LSTSA réelles et aux providers multi-BDD est prévue en P112Q2I3.

## Commandes

```cmd
bin\asap-lstsa-scheduler.cmd enqueue-smoke
bin\asap-lstsa-runner.cmd run-once
```

## Validation

```cmd
tools\automation\p112q2i2_lstsa_runner_scheduler_baseline_check.cmd
```
